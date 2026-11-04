<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Satuan barang (pcs, strip, box, ml, ...). */
#[Fillable(['name'])]
class Unit extends Model {}
