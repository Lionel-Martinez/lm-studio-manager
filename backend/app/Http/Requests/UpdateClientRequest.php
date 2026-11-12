<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;

/**
 * Edición de los datos del cliente. Los proyectos se gestionan por separado.
 */
class UpdateClientRequest extends StoreClientRequest
{
    public function rules(): array
    {
        return Arr::except(parent::rules(), [
            'project', 'project.service_type', 'project.name', 'project.status',
            'project.entry_date', 'project.estimated_start_date', 'project.due_date', 'project.delay_reason',
        ]);
    }
}
