<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name','ssm_number','office_address','phone_number','fax_number','official_email','contact_person','bank','account_number_for_payment','document_path','document_original_name'
    ];
}
