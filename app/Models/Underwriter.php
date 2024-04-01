<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Underwriter extends Model
{
    use HasFactory;
    protected $table = 'underwriters';
    protected $connection = 'mysql';
}
