export interface HelpSection {
  id: string
  title: string
  summary: string
  /** Párrafos y listas; las cadenas que empiezan con "- " se muestran como viñetas. */
  body: string[]
}

export const helpSections: HelpSection[] = [
  {
    id: 'inicio',
    title: 'Cómo está organizado',
    summary: 'El mapa del sistema en un minuto.',
    body: [
      'LM Studio Client Manager es tu libreta privada de clientes: quién es cada cliente, qué proyectos tiene, cuánto te debe y qué hay que hacer esta semana.',
      'El menú de la izquierda tiene un apartado por tema:',
      '- Resumen: lo más importante de hoy (ingresos del mes, proyectos activos, cobros por vencer).',
      '- Clientes: la ficha de cada cliente, con sus proyectos, precios y plan de pago.',
      '- Agenda: entregas, cobros y recordatorios ordenados por fecha. El número entre paréntesis es lo atrasado y lo de hoy.',
      '- Servicios, Reportes y Geografía: miradas de análisis sobre los mismos datos.',
      '- Pagos: todos los cobros, con sus comprobantes.',
      '- Notas: apuntes y plantillas en Markdown.',
      '- Cuenta: tu contraseña, la verificación en dos pasos y el registro de actividad.',
      'Todo lo que cargues en un lugar aparece solo en los demás: no hay que anotar nada dos veces.',
    ],
  },
  {
    id: 'clientes',
    title: 'Clientes',
    summary: 'Alta, edición, estados, desactivar y eliminar.',
    body: [
      'Con «Nuevo cliente» cargás nombre, empresa, contacto, ubicación (país, provincia, departamento y localidad) y la fecha del primer contacto. Podés crear el primer proyecto en el mismo paso.',
      'Cada cliente tiene un estado comercial:',
      '- Nuevo: todavía no empezó a trabajar con vos.',
      '- Activo: tiene trabajo en curso. Pasa solo a «activo» cuando un proyecto suyo entra en desarrollo.',
      '- Inactivo: pausado o dado de baja.',
      'Desactivar no borra nada. Te pide un motivo (falta de pago, sin actividad, lo pidió el cliente u otro) y deja el cliente marcado como inactivo con ese motivo a la vista. Sus proyectos, pagos y notas se conservan; sus entregas dejan de aparecer en la Agenda, pero sus cobros pendientes siguen apareciendo porque la deuda sigue existiendo. «Reactivar» lo vuelve a poner activo.',
      'Eliminar es una baja lógica: el cliente desaparece de las listas, pero su historial (pagos, comprobantes) se conserva y los cobros siguen contando en los totales. Si solo querés pausarlo, usá Desactivar.',
      'El filtro de estado y el buscador de arriba sirven para encontrar clientes rápido. «Exportar CSV» baja la lista para abrirla en Excel.',
    ],
  },
  {
    id: 'proyectos',
    title: 'Proyectos',
    summary: 'Tipos de servicio, estados y fechas.',
    body: [
      'Cada cliente puede tener varios proyectos. Se eligen entre cuatro tipos de servicio: app móvil, app web, menú digital y página estática.',
      'El estado de un proyecto avanza así: Prospecto → En espera → En desarrollo → Listo para entregar → Entregado.',
      'Fechas: la de ingreso (cuándo llegó el pedido), la estimada de inicio y la de entrega. Si algo se demora, anotá el motivo en «Demora»: queda visible en el Resumen.',
      'Un proyecto sin entregar cuya fecha de entrega ya pasó se marca como atrasado en la Agenda y en Reportes.',
    ],
  },
  {
    id: 'precios',
    title: 'Precio y plan de pago',
    summary: 'Pago único, anticipo/saldo, cuotas, cuota mensual, prueba y meses gratis.',
    body: [
      'En Clientes, al desplegar los proyectos de un cliente, el botón «Precio y pagos» abre el plan de pago de ese proyecto.',
      'Forma de pago del precio total:',
      '- Pago único: todo en la fecha de inicio del plan.',
      '- Anticipo y saldo: vos elegís el porcentaje (50/50, 30/70, lo que quieras). El anticipo vence al inicio y el saldo en la fecha de entrega del proyecto.',
      '- En cuotas: de 2 a 60 cuotas mensuales e iguales. Los centavos que sobren van a la última cuota para que la suma dé exacto.',
      'Cuota mensual (mantenimiento, hosting): un importe recurrente por la cantidad de meses que elijas. Podés dar un período de prueba (en días, semanas o meses) y meses gratis: la primera cuota vence después de la prueba más los meses gratis, contando desde el inicio del plan.',
      '«Ver calendario» muestra los cobros que resultarían sin guardar nada. «Guardar plan» solo guarda la configuración. «Guardar y generar pagos» además crea los pagos en la sección Pagos.',
      'Si volvés a generar, se reemplazan solo los pagos pendientes que había creado el plan anterior. Los pagos cargados a mano nunca se tocan, y si alguno del plan ya está cobrado, el sistema no deja rehacer el calendario para no pisar nada.',
      'Todos los importes están en pesos argentinos.',
    ],
  },
  {
    id: 'pagos',
    title: 'Pagos y comprobantes',
    summary: 'Cobrar, estados y comprobantes internos.',
    body: [
      'Cada pago tiene concepto, importe, vencimiento y, cuando se cobra, fecha y medio de pago. El estado se calcula solo: Pendiente (todavía no vence), Vencido (venció y no se cobró) y Cobrado.',
      'Para registrar un cobro usá «Marcar cobrado». El encabezado de Pagos resume lo que hay por cobrar, lo vencido y lo cobrado este mes.',
      'Comprobante: de un pago cobrado podés emitir un comprobante interno, numerado en forma correlativa (C-000001, C-000002…). No es una factura y no tiene validez fiscal. Se puede imprimir o guardar como PDF desde el navegador.',
      'El comprobante guarda una copia de los datos del momento en que se emitió. Con un comprobante vigente no se puede cambiar el importe, el concepto, la fecha ni el medio de pago, ni eliminar el pago. Si te equivocaste, anulá el comprobante y emití uno nuevo: el anulado queda en el historial y su número no se reutiliza.',
      'Los datos de tu negocio que salen impresos (nombre, CUIT, dirección, contacto) se configuran en el archivo .env del servidor (variables BUSINESS_*).',
    ],
  },
  {
    id: 'agenda',
    title: 'Agenda',
    summary: 'Qué hay que hacer y cuándo.',
    body: [
      'La Agenda junta en una sola lista, ordenada por fecha: entregas de proyectos, inicios estimados, cobros pendientes y tus propios recordatorios.',
      'Arriba aparece lo atrasado en rojo; debajo, lo próximo agrupado por día (Hoy, Mañana, lunes 17 de noviembre…). Podés ver los próximos 7, 30 o 90 días.',
      'Los recordatorios son tuyos: «Nuevo recordatorio» pide qué hay que hacer, la fecha y, si querés, un cliente y una nota. Se marcan como «Hecho» cuando los resolvés. Las entregas y los cobros no se marcan acá: desaparecen solos cuando entregás el proyecto o cobrás el pago.',
    ],
  },
  {
    id: 'analisis',
    title: 'Resumen, Reportes, Servicios y Geografía',
    summary: 'Las pantallas para mirar el negocio.',
    body: [
      'Resumen: lo de hoy. Ingresos del mes, clientes con trabajo en curso, prospectos, cobros por vencer y vencidos, y la línea de proyectos con sus fechas.',
      'Reportes: elegís un período (últimos 12 o 6 meses, este año, el año pasado) y ves lo cobrado, lo pendiente y lo vencido, ingresos por mes y por servicio, mejores clientes, proyectos por estado, clientes nuevos y entregas atrasadas.',
      'Servicios: una tarjeta por tipo de servicio con cuántos proyectos hay en cada estado, cuántos clientes lo contrataron, lo cobrado, lo que falta cobrar y las entregas atrasadas.',
      'Geografía: clientes e ingresos por país, provincia, departamento y localidad. Tocá una fila para bajar un nivel y usá las migas de pan de arriba para volver. Los clientes sin ubicación se cuentan aparte.',
      'Las localidades se cargan una sola vez con un comando que trae las oficiales del Estado (API Georef): php artisan app:import-localities.',
    ],
  },
  {
    id: 'notas',
    title: 'Notas',
    summary: 'Documentación y plantillas en Markdown.',
    body: [
      'Las notas sirven para guardar acuerdos, relevamientos, plantillas de presupuesto o cualquier documentación. Se escriben en Markdown sencillo: # títulos, **negrita**, listas con guiones y tablas.',
      'Una nota puede estar suelta o asociada a un cliente y a uno de sus proyectos. El contenido se muestra con formato y los enlaces se abren en una pestaña nueva.',
    ],
  },
  {
    id: 'seguridad',
    title: 'Cuenta y seguridad',
    summary: 'Contraseña, verificación en dos pasos y actividad.',
    body: [
      'El sistema es privado: no hay registro público. Los usuarios se crean desde el servidor con php artisan app:create-user.',
      'Contraseña: se cambia desde Cuenta. Debe tener al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos.',
      'Verificación en dos pasos: en Cuenta escaneás un QR con una app como Google Authenticator o Aegis y confirmás con un código. Desde entonces, al entrar se pide también el código de 6 dígitos. Al activarla se muestran códigos de recuperación, de un solo uso: guardalos en un lugar seguro, porque si perdés el teléfono son la única forma de entrar.',
      'Después de varios intentos fallidos el acceso se bloquea unos minutos. La sesión se cierra sola tras una hora sin actividad.',
      'Registro de actividad (en Cuenta): ingresos, intentos fallidos, cambios de contraseña y altas, cambios y bajas de datos. Se conserva 12 meses y después se limpia solo.',
    ],
  },
  {
    id: 'copias',
    title: 'Copias de seguridad y exportación',
    summary: 'Cómo no perder datos.',
    body: [
      'Copias de seguridad: php artisan app:backup guarda una copia comprimida de la base de datos y conserva las últimas 14. En el servidor se programa para que corra sola cada madrugada (ver SECURITY.md).',
      'Exportar a CSV: Clientes, Proyectos y Pagos se pueden bajar como archivo para abrir en Excel o Google Sheets.',
      'Una copia que nunca se probó no es una copia: de vez en cuando restaurala en otra base para confirmar que funciona.',
    ],
  },
  {
    id: 'glosario',
    title: 'Preguntas frecuentes',
    summary: 'Dudas típicas.',
    body: [
      '¿Por qué un cliente eliminado sigue sumando en los totales? Porque la plata cobrada es real. El historial se conserva siempre.',
      '¿Por qué no veo las entregas de un cliente inactivo en la Agenda? Porque está pausado. Sus cobros pendientes sí aparecen. Reactivalo para volver a ver todo.',
      '¿Puedo editar un pago que ya tiene comprobante? No el importe, concepto, fecha ni medio de pago. Anulá el comprobante, corregí el pago y emití otro.',
      '¿Se pueden cargar pagos a mano además del plan? Sí, desde Pagos. Los pagos manuales y los del plan conviven y no se pisan.',
      '¿Qué pasa si regenero el plan de pago? Se reemplazan solo los pagos pendientes creados por el plan. Los cobrados bloquean la regeneración.',
      '¿El comprobante es una factura? No. Es un recibo interno sin validez fiscal.',
    ],
  },
]
