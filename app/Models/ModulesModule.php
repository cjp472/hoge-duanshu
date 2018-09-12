<?php
/**

 */

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ModulesModule extends Model
{
    protected $table = 'modules_module';
    protected $connection = 'djangodb';
    protected $keyType = 'string';
    public $incrementing = false;
}