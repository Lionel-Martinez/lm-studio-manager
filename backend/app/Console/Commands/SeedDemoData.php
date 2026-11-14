<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Country;
use App\Models\Locality;
use App\Models\Note;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Region;
use App\Models\Reminder;
use App\Support\PaymentPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga 8 clientes ficticios con proyectos, pagos, notas y recordatorios para probar todo el sistema.
 * Todo lo que crea queda marcado como "demo" y se puede quitar con --remove.
 */
class SeedDemoData extends Command
{
    private const MARK = 'demo';

    protected $signature = 'app:seed-demo
        {--remove : Solo borra los datos de prueba, sin crear nada}
        {--force : Permite correrlo con APP_ENV=production}';

    protected $description = 'Crea (o quita con --remove) clientes y movimientos de prueba';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Estás en producción. Si de verdad querés cargar datos de prueba, agregá --force.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            $removed = $this->removeDemo();

            if ($this->option('remove')) {
                $this->info("Datos de prueba borrados: {$removed} clientes.");

                return;
            }

            $this->createDemo();
            $this->info('Listo: 8 clientes de prueba con proyectos, pagos, notas y recordatorios.');
            $this->line('Para quitarlos: php artisan app:seed-demo --remove');
        });

        return self::SUCCESS;
    }

    /** Borra definitivamente (no es baja lógica) todo lo marcado como demo. */
    private function removeDemo(): int
    {
        $clients = Client::withTrashed()->where('source', self::MARK)->get();
        $ids = $clients->modelKeys();

        $paymentIds = Payment::withTrashed()->whereIn('client_id', $ids)->pluck('id');
        Receipt::whereIn('payment_id', $paymentIds)->delete();
        Payment::withTrashed()->whereIn('client_id', $ids)->forceDelete();
        Note::withTrashed()->whereIn('client_id', $ids)->orWhere('title', 'like', '[Demo]%')->forceDelete();
        Reminder::withTrashed()->whereIn('client_id', $ids)->orWhere('title', 'like', '[Demo]%')->forceDelete();
        foreach ($clients as $client) {
            $client->projects()->withTrashed()->forceDelete();
            $client->forceDelete();
        }

        return count($ids);
    }

    private function place(string $department): array
    {
        $country = Country::where('iso2', 'AR')->first();
        $region = Region::where('region_type', 'department')->where('name', $department)->first();
        $locality = $region ? Locality::where('region_id', $region->id)->orderBy('name')->first() : null;

        return [
            'country_id' => $country?->id,
            'region_id' => $region?->id,
            'locality_id' => $locality?->id,
        ];
    }

    private function createDemo(): void
    {
        $d = fn (int $days) => today()->addDays($days)->toDateString();

        // 1. Menú digital entregado y cobrado 50/50, con comprobante.
        $espiga = $this->client('Marcela', 'Quiroga', 'Panadería La Espiga', 'active', 'Orán', 'Recomendación de otro cliente');
        $menu = $this->project($espiga, 'digital_menu', 'Menú QR del local', 'delivered', $d(-120), $d(-110), $d(-90));
        $this->plan($menu, ['total_price' => 80000, 'payment_mode' => 'split', 'deposit_percent' => 50, 'plan_start_date' => $d(-115)]);
        $this->payAll($menu, withReceipt: true);

        // 2. Aplicación web en desarrollo, atrasada, con cuotas (una cobrada, una vencida, una por vencer).
        $ferre = $this->client('Julián', 'Rojas', 'Ferretería Norte', 'active', 'Orán', 'Instagram');
        $web = $this->project($ferre, 'web_app', 'Sistema de pedidos', 'in_progress', $d(-75), $d(-60), $d(-5), 'El cliente tardó en enviar el catálogo de productos');
        $this->plan($web, ['total_price' => 360000, 'payment_mode' => 'installments', 'installments_count' => 3, 'plan_start_date' => $d(-65)]);
        $this->payFirst($web, 1);

        // 3. Sitio estático listo para entregar, pago único pendiente.
        $dra = $this->client('Lucía', 'Fernández', null, 'active', 'Orán', 'Google');
        $sitio = $this->project($dra, 'static_site', 'Sitio del consultorio', 'ready', $d(-20), $d(-15), $d(4));
        $this->plan($sitio, ['total_price' => 120000, 'payment_mode' => 'single', 'plan_start_date' => $d(4)]);

        // 4. Menú digital en espera, con anticipo cobrado y saldo a la entrega.
        $cafe = $this->client('Tomás', 'Aguirre', 'Café del Cerro', 'new', 'Orán', 'WhatsApp');
        $cafeMenu = $this->project($cafe, 'digital_menu', null, 'waiting', $d(-10), $d(12), $d(30));
        $this->plan($cafeMenu, ['total_price' => 90000, 'payment_mode' => 'split', 'deposit_percent' => 30, 'plan_start_date' => $d(-8)]);
        $this->payFirst($cafeMenu, 1);

        // 5. Prospecto sin precio todavía.
        $taller = $this->client('Darío', 'Rojas', 'Taller Mecánico Rojas', 'new', 'Orán', 'Pasó por el local');
        $this->project($taller, 'mobile_app', 'App de turnos', 'prospect', $d(-3), null, null);

        // 6. Cliente con cuota mensual: prueba de 2 semanas + 1 mes gratis.
        $gym = $this->client('Carolina', 'Vega', 'Gimnasio Fuerza Norte', 'active', 'Orán', 'Recomendación');
        $gymWeb = $this->project($gym, 'web_app', 'Gestión de socios', 'delivered', $d(-50), $d(-45), $d(-14));
        $this->plan($gymWeb, [
            'total_price' => 240000, 'payment_mode' => 'installments', 'installments_count' => 2, 'plan_start_date' => $d(-45),
            'monthly_fee' => 15000, 'monthly_months' => 12, 'free_months' => 1, 'trial_value' => 2, 'trial_unit' => 'weeks',
        ]);
        $this->payFirst($gymWeb, 2);

        // 7. Cliente desactivado por falta de pago, con una cuota mensual vencida.
        $estudio = $this->client('Raúl', 'Paz', 'Estudio Contable Paz', 'inactive', 'Orán', 'Evento local');
        $estudioWeb = $this->project($estudio, 'static_site', 'Sitio institucional', 'delivered', $d(-200), $d(-190), $d(-170));
        $this->plan($estudioWeb, ['total_price' => 100000, 'payment_mode' => 'single', 'plan_start_date' => $d(-170), 'monthly_fee' => 8000, 'monthly_months' => 6]);
        $this->payFirst($estudioWeb, 1);
        $estudio->update(['deactivated_at' => now()->subDays(40), 'deactivation_reason' => 'non_payment', 'deactivation_note' => 'Debe 4 cuotas mensuales; no responde mensajes']);

        // 8. Otro departamento, para ver el reparto geográfico.
        $hotel = $this->client('Silvia', 'Mamaní', 'Hotel Los Pinos', 'active', 'Capital', 'LinkedIn');
        $hotelMenu = $this->project($hotel, 'digital_menu', 'Carta del restaurante', 'in_progress', $d(-18), $d(-10), $d(15));
        $this->plan($hotelMenu, ['total_price' => 150000, 'payment_mode' => 'split', 'deposit_percent' => 50, 'plan_start_date' => $d(-12)]);
        $this->payFirst($hotelMenu, 1);

        // Notas.
        Note::create(['client_id' => $ferre->id, 'project_id' => $web->id, 'title' => '[Demo] Reunión de relevamiento', 'body' => "# Relevamiento\n\n- Hoy usan **cuaderno** para anotar pedidos\n- Quieren ver el stock desde el celular\n- Falta el catálogo en Excel\n\n> Acordamos entrega en 45 días."]);
        Note::create(['client_id' => $dra->id, 'title' => '[Demo] Contenido del sitio', 'body' => "## Secciones\n\n1. Inicio\n2. Tratamientos\n3. Turnos (WhatsApp)\n\nEnviar las fotos del consultorio antes del viernes."]);
        Note::create(['title' => '[Demo] Plantilla de presupuesto', 'body' => "# Presupuesto\n\n**Cliente:** …\n**Servicio:** …\n\n| Concepto | Importe |\n|---|---|\n| Diseño | $ |\n| Desarrollo | $ |\n\nForma de pago: 50% anticipo, 50% a la entrega."]);

        // Recordatorios.
        Reminder::create(['client_id' => $ferre->id, 'title' => '[Demo] Pedir el catálogo de productos', 'remind_on' => $d(-2)]);
        Reminder::create(['client_id' => $dra->id, 'title' => '[Demo] Entregar el sitio y cobrar', 'remind_on' => $d(0), 'notes' => 'Llevar el comprobante']);
        Reminder::create(['title' => '[Demo] Renovar dominio .com.ar', 'remind_on' => $d(7)]);
    }

    private function client(string $first, string $last, ?string $company, string $status, string $department, string $source): Client
    {
        $client = Client::create([
            'first_name' => $first,
            'last_name' => $last,
            'company_name' => $company,
            'client_type' => $company ? 'company' : 'person',
            'commercial_status' => $status,
            'email' => strtolower(preg_replace('/\W+/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $company ?? $first.$last))).'@ejemplo.com',
            'whatsapp' => '3878 '.random_int(400000, 499999),
            'first_contact_at' => today()->subDays(random_int(10, 150))->toDateString(),
            'notes' => "Origen: {$source}. Cliente de prueba.",
        ] + $this->place($department));

        // `source` queda reservado para marcar lo que es de prueba.
        $client->forceFill(['source' => self::MARK])->save();

        return $client;
    }

    private function project(Client $client, string $type, ?string $name, string $status, string $entry, ?string $start, ?string $due, ?string $delay = null)
    {
        return $client->projects()->create([
            'service_type' => $type, 'name' => $name, 'status' => $status,
            'entry_date' => $entry, 'estimated_start_date' => $start, 'due_date' => $due, 'delay_reason' => $delay,
        ]);
    }

    /** Guarda el plan de pago en el proyecto y crea sus pagos. */
    private function plan($project, array $config): void
    {
        $project->update($config);
        PaymentPlan::createPayments($project->refresh(), PaymentPlan::schedule($project));
    }

    /** Marca como cobrados los primeros `$count` pagos del plan. */
    private function payFirst($project, int $count): void
    {
        Payment::where('project_id', $project->id)->orderBy('due_date')->limit($count)->get()
            ->each(fn (Payment $p) => $p->update(['paid_at' => $p->due_date->toDateString(), 'method' => 'Transferencia']));
    }

    private function payAll($project, bool $withReceipt = false): void
    {
        $this->payFirst($project, 99);

        if ($withReceipt) {
            $payment = Payment::where('project_id', $project->id)->orderBy('due_date')->first();

            Receipt::create([
                'payment_id' => $payment->id,
                'number' => (int) Receipt::max('number') + 1,
                'client_name' => $project->client->display_name,
                'client_tax_id' => $project->client->tax_id,
                'concept' => $payment->concept,
                'project_name' => $project->name,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at,
                'method' => $payment->method,
                'issued_at' => now(),
            ]);
        }
    }
}
