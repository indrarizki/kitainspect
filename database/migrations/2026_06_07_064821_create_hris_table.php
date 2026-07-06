<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        // Step 1: Create all base tables WITHOUT foreign keys
        Schema::create('regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 7);
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 7);
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shiftments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 7);
            $table->string('name');
            $table->time('startHour');
            $table->time('endHour');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('absent_reasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 1);
            $table->string('code', 7);
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('holidayDate');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 1);
            $table->string('letterNumber', 27);
            $table->string('subject');
            $table->string('description')->nullable();
            $table->date('startDate');
            $table->date('endDate')->nullable();
            $table->date('signedDate');
            $table->json('tags')->nullable();
            $table->boolean('used');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('educational_institutes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('education_titles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('shortName', 5);
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('salary_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 7);
            $table->string('name');
            $table->string('state', 1);
            $table->boolean('fixed');
            $table->timestamps();
            $table->softDeletes();
        });

        // Step 2: Create hierarchical tables (with nullable parent_id, FK added later)
        // Schema::create('companies', function (Blueprint $table) {
        //     $table->uuid('id')->primary();
        //     $table->string('code', 7);
        //     $table->string('name');
        //     $table->date('birthDay');
        //     $table->string('email');
        //     $table->string('taxNumber');
        //     $table->timestamps();
        //     $table->softDeletes();
        //     $table->uuid('parent_id')->nullable();
        //     $table->uuid('address_id')->nullable();
        // });

        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 7);
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('parent_id')->nullable();
        });

        Schema::create('job_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 7);
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('parent_id')->nullable();
        });

        Schema::create('job_titles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 9);
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('job_level_id')->nullable();
        });

        Schema::create('skill_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('parent_id')->nullable();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('skill_group_id')->nullable();
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->smallInteger('year');
            $table->smallInteger('month');
            $table->boolean('closed');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('company_id')->nullable();
        });

        // Step 3: Create address tables (before employees)
        Schema::create('company_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('address');
            $table->string('postalCode', 5);
            $table->string('phoneNumber', 17);
            $table->string('faxNumber', 11);
            $table->boolean('defaultAddress');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('company_id')->nullable();
            $table->uuid('region_id')->nullable();
            $table->uuid('city_id')->nullable();
        });

        Schema::create('employee_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('address');
            $table->string('postalCode', 5);
            $table->string('phoneNumber', 17);
            $table->string('faxNumber', 11)->nullable();
            $table->boolean('defaultAddress');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
            $table->uuid('region_id')->nullable();
            $table->uuid('city_id')->nullable();
        });

        // Step 4: Create employees (after all dependency tables)
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('joinDate');
            $table->string('employeeStatus', 1);
            $table->string('code', 17);
            $table->string('fullName');
            $table->string('gender', 1);
            $table->date('dateOfBirth');
            $table->string('identityNumber', 27);
            $table->string('identityType', 1);
            $table->string('maritalStatus', 1);
            $table->string('email');
            $table->integer('leaveBalance')->nullable();
            $table->string('taxGroup', 3);
            $table->date('resignDate')->nullable();
            $table->boolean('haveOvertimeBenefit');
            $table->string('riskRatio', 3);
            $table->string('username');
            $table->string('password');
            $table->json('roles');
            $table->string('profileImage')->nullable();
            $table->integer('profileSize')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('contract_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->uuid('department_id')->nullable();
            $table->uuid('joblevel_id')->nullable();
            $table->uuid('jobtitle_id')->nullable();
            $table->uuid('supervisor_id')->nullable();
            $table->uuid('region_of_birth_id')->nullable();
            $table->uuid('city_of_birth_id')->nullable();
            $table->uuid('address_id')->nullable();
            $table->string('noAccount');
            
        });

        // Step 5: Create dependent tables (that reference employees)
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->smallInteger('year');
            $table->smallInteger('month');
            $table->integer('totalWorkday');
            $table->integer('totalIn');
            $table->integer('totalLoyality');
            $table->integer('totalAbsent');
            $table->integer('totalOvertime');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('attendanceDate');
            $table->string('description')->nullable();
            $table->time('checkIn')->nullable();
            $table->time('checkOut')->nullable();
            $table->integer('earlyIn');
            $table->integer('earlyOut');
            $table->integer('lateIn');
            $table->integer('lateOut');
            $table->boolean('absent');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
            $table->uuid('shiftment_id')->nullable();
            $table->uuid('reason_id')->nullable();
        });

        Schema::create('career_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('description', 11);
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->uuid('department_id')->nullable();
            $table->uuid('joblevel_id')->nullable();
            $table->uuid('jobtitle_id')->nullable();
            $table->uuid('supervisor_id')->nullable();
            $table->uuid('contract_id')->nullable();
        });

        Schema::create('leaves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('leaveDate');
            $table->smallInteger('amount');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
            $table->uuid('reason_id')->nullable();
        });

        Schema::create('job_mutations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 1);
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
            $table->uuid('old_company_id')->nullable();
            $table->uuid('old_department_id')->nullable();
            $table->uuid('old_joblevel_id')->nullable();
            $table->uuid('old_jobtitle_id')->nullable();
            $table->uuid('old_supervisor_id')->nullable();
            $table->uuid('new_company_id')->nullable();
            $table->uuid('new_department_id')->nullable();
            $table->uuid('new_joblevel_id')->nullable();
            $table->uuid('new_jobtitle_id')->nullable();
            $table->uuid('new_supervisor_id')->nullable();
            $table->uuid('contract_id')->nullable();
        });

        Schema::create('overtimes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('overtimeDate');
            $table->time('startHour');
            $table->time('endHour');
            $table->float('rawValue');
            $table->float('calculatedValue');
            $table->boolean('holiday');
            $table->boolean('overday');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
            $table->uuid('shiftment_id')->nullable();
            $table->uuid('approved_by_id')->nullable();
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('takeHomePay')->nullable();
            $table->string('takeHomePayKey')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
            $table->uuid('period_id')->nullable();
        });

        Schema::create('company_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('benefitValue');
            $table->string('benefitKey')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('payroll_id')->nullable();
            $table->uuid('component_id')->nullable();
        });

        Schema::create('payroll_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('benefitValue');
            $table->string('benefitKey')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('payroll_id')->nullable();
            $table->uuid('component_id')->nullable();
        });

        Schema::create('job_placements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('active');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->uuid('department_id')->nullable();
            $table->uuid('joblevel_id')->nullable();
            $table->uuid('jobtitle_id')->nullable();
            $table->uuid('supervisor_id')->nullable();
            $table->uuid('contract_id')->nullable();
        });

        Schema::create('salary_allowances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->smallInteger('year');
            $table->smallInteger('month');
            $table->text('benefitValue')->nullable();
            $table->string('benefitKey')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
            $table->uuid('component_id')->nullable();
        });

        Schema::create('salary_benefits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('benefitValue')->nullable();
            $table->string('benefitKey')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
            $table->uuid('component_id')->nullable();
        });

        Schema::create('salary_benefit_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('newBenefitValue')->nullable();
            $table->text('oldBenefitValue')->nullable();
            $table->string('benefitKey')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
            $table->uuid('component_id')->nullable();
            $table->uuid('contract_id')->nullable();
        });

        Schema::create('taxs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('taxGroup', 3);
            $table->text('untaxable')->nullable();
            $table->text('taxable')->nullable();
            $table->text('taxValue')->nullable();
            $table->string('taxKey')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('period_id')->nullable();
            $table->uuid('employee_id')->nullable();
        });

        Schema::create('tax_group_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('oldTaxGroup', 3);
            $table->string('newTaxGroup', 3)->nullable();
            $table->string('oldRiskRatio', 3);
            $table->string('newRiskRatio', 3)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
        });

        Schema::create('workshifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('description')->nullable();
            $table->date('startDate');
            $table->date('endDate');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('employee_id')->nullable();
            $table->uuid('shiftment_id')->nullable();
        });

        Schema::create('company_departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('department_id')->nullable();
            $table->uuid('company_id')->nullable();
        });

        // Step 6: NOW add ALL foreign keys after all tables are created
        // This avoids circular dependencies and reference errors

        // Schema::table('regions', function (Blueprint $table) {
        //     // No self-referential FK needed
        // });

        // Schema::table('cities', function (Blueprint $table) {
        //     $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
        // });

        // Schema::table('companies', function (Blueprint $table) {
        //     $table->foreign('parent_id')->references('id')->on('companies')->nullOnDelete();
        //     $table->foreign('address_id')->references('id')->on('company_addresses')->nullOnDelete();
        // });

        // Schema::table('departments', function (Blueprint $table) {
        //     $table->foreign('parent_id')->references('id')->on('departments')->nullOnDelete();
        // });

        // Schema::table('job_levels', function (Blueprint $table) {
        //     $table->foreign('parent_id')->references('id')->on('job_levels')->nullOnDelete();
        // });

        // Schema::table('job_titles', function (Blueprint $table) {
        //     $table->foreign('job_level_id')->references('id')->on('job_levels')->nullOnDelete();
        // });

        // Schema::table('skill_groups', function (Blueprint $table) {
        //     $table->foreign('parent_id')->references('id')->on('skill_groups')->nullOnDelete();
        // });

        // Schema::table('skills', function (Blueprint $table) {
        //     $table->foreign('skill_group_id')->references('id')->on('skill_groups')->nullOnDelete();
        // });

        // Schema::table('payroll_periods', function (Blueprint $table) {
        //     $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        // });

        // Schema::table('company_addresses', function (Blueprint $table) {
        //     $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        //     $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
        //     $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
        // });

        // Schema::table('employee_addresses', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
        //     $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
        // });

        // Schema::table('employees', function (Blueprint $table) {
        //     $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
        //     $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        //     $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        //     $table->foreign('joblevel_id')->references('id')->on('job_levels')->nullOnDelete();
        //     $table->foreign('jobtitle_id')->references('id')->on('job_titles')->nullOnDelete();
        //     $table->foreign('supervisor_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('region_of_birth_id')->references('id')->on('regions')->nullOnDelete();
        //     $table->foreign('city_of_birth_id')->references('id')->on('cities')->nullOnDelete();
        //     $table->foreign('address_id')->references('id')->on('employee_addresses')->nullOnDelete();
        // });

        // Schema::table('attendance_summaries', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        // });

        // Schema::table('attendances', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('shiftment_id')->references('id')->on('shiftments')->nullOnDelete();
        //     $table->foreign('reason_id')->references('id')->on('absent_reasons')->nullOnDelete();
        // });

        // Schema::table('career_histories', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        //     $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        //     $table->foreign('joblevel_id')->references('id')->on('job_levels')->nullOnDelete();
        //     $table->foreign('jobtitle_id')->references('id')->on('job_titles')->nullOnDelete();
        //     $table->foreign('supervisor_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
        // });

        // Schema::table('leaves', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('reason_id')->references('id')->on('absent_reasons')->nullOnDelete();
        // });

        // Schema::table('job_mutations', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('old_company_id')->references('id')->on('companies')->nullOnDelete();
        //     $table->foreign('old_department_id')->references('id')->on('departments')->nullOnDelete();
        //     $table->foreign('old_joblevel_id')->references('id')->on('job_levels')->nullOnDelete();
        //     $table->foreign('old_jobtitle_id')->references('id')->on('job_titles')->nullOnDelete();
        //     $table->foreign('old_supervisor_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('new_company_id')->references('id')->on('companies')->nullOnDelete();
        //     $table->foreign('new_department_id')->references('id')->on('departments')->nullOnDelete();
        //     $table->foreign('new_joblevel_id')->references('id')->on('job_levels')->nullOnDelete();
        //     $table->foreign('new_jobtitle_id')->references('id')->on('job_titles')->nullOnDelete();
        //     $table->foreign('new_supervisor_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
        // });

        // Schema::table('overtimes', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('shiftment_id')->references('id')->on('shiftments')->nullOnDelete();
        //     $table->foreign('approved_by_id')->references('id')->on('employees')->nullOnDelete();
        // });

        // Schema::table('payrolls', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('period_id')->references('id')->on('payroll_periods')->nullOnDelete();
        // });

        // Schema::table('company_costs', function (Blueprint $table) {
        //     $table->foreign('payroll_id')->references('id')->on('payrolls')->nullOnDelete();
        //     $table->foreign('component_id')->references('id')->on('salary_components')->nullOnDelete();
        // });

        // Schema::table('payroll_details', function (Blueprint $table) {
        //     $table->foreign('payroll_id')->references('id')->on('payrolls')->nullOnDelete();
        //     $table->foreign('component_id')->references('id')->on('salary_components')->nullOnDelete();
        // });

        // Schema::table('job_placements', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        //     $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        //     $table->foreign('joblevel_id')->references('id')->on('job_levels')->nullOnDelete();
        //     $table->foreign('jobtitle_id')->references('id')->on('job_titles')->nullOnDelete();
        //     $table->foreign('supervisor_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
        // });

        // Schema::table('salary_allowances', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('component_id')->references('id')->on('salary_components')->nullOnDelete();
        // });

        // Schema::table('salary_benefits', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('component_id')->references('id')->on('salary_components')->nullOnDelete();
        // });

        // Schema::table('salary_benefit_histories', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('component_id')->references('id')->on('salary_components')->nullOnDelete();
        //     $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
        // });

        // Schema::table('taxs', function (Blueprint $table) {
        //     $table->foreign('period_id')->references('id')->on('payroll_periods')->nullOnDelete();
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        // });

        // Schema::table('tax_group_history', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        // });

        // Schema::table('workshifts', function (Blueprint $table) {
        //     $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        //     $table->foreign('shiftment_id')->references('id')->on('shiftments')->nullOnDelete();
        // });

        // Schema::table('company_departments', function (Blueprint $table) {
        //     $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        //     $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        // });

        // Schema::enableForeignKeyConstraints();
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('company_departments');
        Schema::dropIfExists('workshifts');
        Schema::dropIfExists('tax_group_history');
        Schema::dropIfExists('taxs');
        Schema::dropIfExists('salary_benefit_histories');
        Schema::dropIfExists('salary_benefits');
        Schema::dropIfExists('salary_allowances');
        Schema::dropIfExists('job_placements');
        Schema::dropIfExists('payroll_details');
        Schema::dropIfExists('company_costs');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('overtimes');
        Schema::dropIfExists('job_mutations');
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('career_histories');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('attendance_summaries');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('employee_addresses');
        Schema::dropIfExists('company_addresses');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('skill_groups');
        Schema::dropIfExists('job_titles');
        Schema::dropIfExists('job_levels');
        Schema::dropIfExists('departments');
        // Schema::dropIfExists('companies');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('education_titles');
        Schema::dropIfExists('educational_institutes');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('absent_reasons');
        Schema::dropIfExists('shiftments');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('contracts');

        Schema::enableForeignKeyConstraints();
    }
};
