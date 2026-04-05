<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cliente_id',
    'uas_class',
    'manufacturer_name',
    'model',
    'controller_serial_number',
    'registration_number',
    'mtom_weight',
    'remote_id_number',
    'class_marking',
    'band_frequency',
    'color',
    'payload',
    'vhf_equipment',
    'emergency_equipment',
    'insurance_policy_number',
    'insurance_valid_until',
    'insurance_company_name',
])]
#[Casts([
    'insurance_valid_until' => 'date',
])]
class Dron extends Model
{
    protected $table = 'drones';

    /**
     * Cada dron pertenece a un cliente concreto.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
