<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->date('birthday')->nullable()->after('phone');
            $table->string('address', 255)->nullable()->after('birthday');
            $table->string('country', 100)->nullable()->after('address');
            $table->string('city', 100)->nullable()->after('country');
            $table->string('postal_code', 32)->nullable()->after('city');
            $table->string('company', 180)->nullable()->after('postal_code');
            $table->string('position', 180)->nullable()->after('company');
            $table->string('company_contact', 100)->nullable()->after('position');
            $table->string('employee_id', 64)->nullable()->after('company_contact');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn(['birthday', 'address', 'country', 'city', 'postal_code', 'company', 'position', 'company_contact', 'employee_id']);
        });
    }
};
