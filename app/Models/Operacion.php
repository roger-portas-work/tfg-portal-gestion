<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cliente_id',
    'piloto_id',
    'dron_id',
    'reference',
    'operation_date',
    'estimated_filming_schedule',
    'address',
    'country',
    'city',
    'province',
    'postal_code',
    'google_maps_link',
    'altitude',
    'operation_radius',
    'extra_information',
    'video_objective',
    'end_client',
    'production_company_name',
    'production_contact_phone',
    'environment_type',
    'people_present',
    'prior_permits_notes',
    'location',
    'description',
])]
#[Casts([
    'operation_date' => 'date',
    'altitude' => 'decimal:2',
    'operation_radius' => 'decimal:2',
    'people_present' => 'boolean',
])]
class Operacion extends Model
{
    protected $table = 'operaciones';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function piloto(): BelongsTo
    {
        return $this->belongsTo(Piloto::class);
    }

    public function dron(): BelongsTo
    {
        return $this->belongsTo(Dron::class);
    }
}
