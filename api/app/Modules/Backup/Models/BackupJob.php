<?php
namespace App\Modules\Backup\Models;
use Illuminate\Database\Eloquent\Model;
class BackupJob extends Model {
    protected $fillable = ['filename','remote_ref','checksum','size','status','uploaded','verified','error','started_at','finished_at'];
    protected $casts = ['uploaded'=>'boolean','verified'=>'boolean','started_at'=>'datetime','finished_at'=>'datetime'];
}
