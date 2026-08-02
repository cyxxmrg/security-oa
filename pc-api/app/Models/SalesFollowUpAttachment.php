<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesFollowUpAttachment extends Model
{
    use HasFactory;
    protected $table = 'sales_follow_up_attachments';
    protected $fillable = ['follow_up_id', 'name', 'path', 'mime', 'size'];
}
