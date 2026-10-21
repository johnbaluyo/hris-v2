# Legacy migration and field inventory

Source revision: `c8973c067292967581b0ae5a2fe8493f905fed4c`. Generated from the working tree by [generate-inventories.cjs](../tools/generate-inventories.cjs). This is static source evidence, not a running application or live database inspection. Re-run after source changes.

This is an ordered migration ledger of up() operations, not a reconstructed final schema. Later operations may change or remove fields/tables. Raw SQL, conditional code, computed column names and model-only fields require manual review. Only names, types and modifiers are included; seed records and credentials are excluded. Obtain a schema-only live database export before building the importer.

Migration files: **192**.

## 2014_10_12_000000_create_users_table.php

[database/migrations/2014_10_12_000000_create_users_table.php](../../../database/migrations/2014_10_12_000000_create_users_table.php)

- **create** `users`: `(implicit/multiple columns)`: id; `firstname`: string; `middlename`: string; `lastname`: string; `division_id`: integer; `position_id`: integer [nullable]; `employee_type`: string; `user_type`: string; `date_hired`: date; `step_increment`: integer [nullable]; `email`: string [unique, nullable]; `email_verified_at`: timestamp [nullable]; `password`: string [nullable]; `atms_id`: string [nullable]; `(implicit/multiple columns)`: rememberToken; `(implicit/multiple columns)`: timestamps.

## 2014_10_12_100000_create_password_resets_table.php

[database/migrations/2014_10_12_100000_create_password_resets_table.php](../../../database/migrations/2014_10_12_100000_create_password_resets_table.php)

- **create** `password_resets`: `email`: string [index]; `token`: string; `created_at`: timestamp [nullable].

## 2019_08_19_000000_create_failed_jobs_table.php

[database/migrations/2019_08_19_000000_create_failed_jobs_table.php](../../../database/migrations/2019_08_19_000000_create_failed_jobs_table.php)

- **create** `failed_jobs`: `(implicit/multiple columns)`: id; `connection`: text; `queue`: text; `payload`: longText; `exception`: longText; `failed_at`: timestamp.

## 2021_04_09_082743_division_table.php

[database/migrations/2021_04_09_082743_division_table.php](../../../database/migrations/2021_04_09_082743_division_table.php)

- **create** `divisions`: `(implicit/multiple columns)`: id; `name`: string; `(implicit/multiple columns)`: timestamps.

## 2021_04_09_082851_positions_table.php

[database/migrations/2021_04_09_082851_positions_table.php](../../../database/migrations/2021_04_09_082851_positions_table.php)

- **create** `positions`: `(implicit/multiple columns)`: id; `title`: string; `division_id`: integer; `salary_grade`: integer; `(implicit/multiple columns)`: timestamps.

## 2021_04_12_114435_create_official_businesses_table.php

[database/migrations/2021_04_12_114435_create_official_businesses_table.php](../../../database/migrations/2021_04_12_114435_create_official_businesses_table.php)

- **create** `official_businesses`: `(implicit/multiple columns)`: id; `emp_id`: integer; `date_filed`: date; `ob_date`: date; `departure_time`: time; `return_time`: time; `from`: string; `destination`: string; `departure_option`: string; `return_option`: string; `purpose`: string; `old`: json [nullable]; `(implicit/multiple columns)`: timestamps.

## 2021_04_12_115759_create_statuses_table.php

[database/migrations/2021_04_12_115759_create_statuses_table.php](../../../database/migrations/2021_04_12_115759_create_statuses_table.php)

- **create** `statuses`: `(implicit/multiple columns)`: id; `request_id`: integer; `request_type`: string; `status`: string; `person_in_charge`: integer; `(implicit/multiple columns)`: timestamps.

## 2021_04_14_051907_dtr_table.php

[database/migrations/2021_04_14_051907_dtr_table.php](../../../database/migrations/2021_04_14_051907_dtr_table.php)

- **create** `dtr`: `(implicit/multiple columns)`: id; `user_id`: integer; `time_in`: dateTime [nullable]; `time_out`: dateTime [nullable]; `hours_rendered`: integer [nullable]; `notes`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2021_04_20_194526_hourse_rendered_decimal.php

[database/migrations/2021_04_20_194526_hourse_rendered_decimal.php](../../../database/migrations/2021_04_20_194526_hourse_rendered_decimal.php)

- **table** `dtr`: `hours_rendered`: decimal [change].

## 2021_05_02_190440_create_overtime_table.php

[database/migrations/2021_05_02_190440_create_overtime_table.php](../../../database/migrations/2021_05_02_190440_create_overtime_table.php)

- **create** `overtimes`: `(implicit/multiple columns)`: id; `emp_id`: integer; `date_filed`: date; `day_type`: string; `start_time`: time; `ot_hours`: integer; `ot_type`: string; `purpose`: string; `file`: string; `created_by`: integer; `(implicit/multiple columns)`: timestamps.

## 2021_05_04_083838_leave_tbl.php

[database/migrations/2021_05_04_083838_leave_tbl.php](../../../database/migrations/2021_05_04_083838_leave_tbl.php)

- **create** `leaves`: `(implicit/multiple columns)`: id; `user_id`: integer; `filing_date`: date; `type_of_leave`: string; `commutation`: integer [nullable]; `recommendation`: string [nullable]; `disapproval_due_to`: string [nullable]; `days_with_pay`: integer [nullable]; `days_wo_pay`: integer [nullable]; `others_specify`: string; `disapproved_due_to`: string [nullable]; `status`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2021_05_04_102326_leave_form_specify.php

[database/migrations/2021_05_04_102326_leave_form_specify.php](../../../database/migrations/2021_05_04_102326_leave_form_specify.php)

- **table** `leaves`: `vl_type`: integer [nullable, after]; `vl_type_remark`: string [nullable, after]; `vl_spent`: integer [after, nullable]; `vl_spent_remark`: string [nullable, after]; `sl_type`: integer [nullable, after]; `sl_type_remark`: string [after, nullable]; `spl_remark`: string [nullable, after].

## 2021_05_04_114603_leave_dates_table.php

[database/migrations/2021_05_04_114603_leave_dates_table.php](../../../database/migrations/2021_05_04_114603_leave_dates_table.php)

- **create** `leave_dates`: `(implicit/multiple columns)`: id; `leave_id`: integer; `inclusive_date`: date; `status`: string [nullable]; `remarks`: string [nullable]; `with_pay`: integer [nullable]; `(implicit/multiple columns)`: timestamps.

## 2021_05_07_110845_others_nullable.php

[database/migrations/2021_05_07_110845_others_nullable.php](../../../database/migrations/2021_05_07_110845_others_nullable.php)

- **table** `leaves`: `others_specify`: string [nullable, change].

## 2021_05_07_145730_holidays_table.php

[database/migrations/2021_05_07_145730_holidays_table.php](../../../database/migrations/2021_05_07_145730_holidays_table.php)

- **create** `holidays`: `(implicit/multiple columns)`: id; `holiday`: string; `holiday_date`: date; `type_of_holiday`: string; `(implicit/multiple columns)`: timestamps.

## 2021_05_11_152445_leave_logs_tbl.php

[database/migrations/2021_05_11_152445_leave_logs_tbl.php](../../../database/migrations/2021_05_11_152445_leave_logs_tbl.php)

- **create** `leave_log`: `(implicit/multiple columns)`: id; `user_id`: integer; `type_of_leave`: string; `activity`: string; `credit_amount`: double(12,3); `total_credits`: double(12,3); `status`: string; `(implicit/multiple columns)`: timestamps.

## 2021_05_17_011934_days_pay.php

[database/migrations/2021_05_17_011934_days_pay.php](../../../database/migrations/2021_05_17_011934_days_pay.php)

- **table** `leaves`: `days_with_pay`: decimal(4,3) [change]; `days_wo_pay`: decimal(4,3) [change].

## 2021_05_17_220647_create_overtime_dates_table.php

[database/migrations/2021_05_17_220647_create_overtime_dates_table.php](../../../database/migrations/2021_05_17_220647_create_overtime_dates_table.php)

- **create** `overtime_dates`: `(implicit/multiple columns)`: id; `overtime_id`: integer; `inclusive_date`: date; `used_coc_hours`: double(8,3) [nullable]; `(implicit/multiple columns)`: timestamps.

## 2021_05_20_002529_create_dtr_activities_table.php

[database/migrations/2021_05_20_002529_create_dtr_activities_table.php](../../../database/migrations/2021_05_20_002529_create_dtr_activities_table.php)

- **create** `dtr_activities`: `(implicit/multiple columns)`: id; `user_id`: integer; `status`: boolean; `date_time_log`: dateTime; `remarks`: string [nullable]; `table_id`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2021_06_07_151443_dtr_lunch_time.php

[database/migrations/2021_06_07_151443_dtr_lunch_time.php](../../../database/migrations/2021_06_07_151443_dtr_lunch_time.php)

- **table** `dtr`: `lunch_out`: dateTime [nullable, after]; `lunch_in`: dateTime [nullable, after].

## 2021_06_16_171754_create_c_t_o_s_table.php

[database/migrations/2021_06_16_171754_create_c_t_o_s_table.php](../../../database/migrations/2021_06_16_171754_create_c_t_o_s_table.php)

- **create** `c_t_o_s`: `(implicit/multiple columns)`: id; `emp_id`: integer; `date_filed`: date; `(implicit/multiple columns)`: timestamps.

## 2021_06_25_210055_ot_out_in.php

[database/migrations/2021_06_25_210055_ot_out_in.php](../../../database/migrations/2021_06_25_210055_ot_out_in.php)

- **table** `dtr`: `ot_in`: dateTime [nullable, after]; `ot_out`: dateTime [nullable, after].

## 2021_06_25_231429_sl_vl_computation_table_1.php

[database/migrations/2021_06_25_231429_sl_vl_computation_table_1.php](../../../database/migrations/2021_06_25_231429_sl_vl_computation_table_1.php)

- **create** `table_1`: `(implicit/multiple columns)`: id; `number_of_months`: integer; `vl_earned`: double(12,3); `sl_earned`: double(12,3); `(implicit/multiple columns)`: timestamps.

## 2021_06_25_231608_sl_vl_computation_table_2.php

[database/migrations/2021_06_25_231608_sl_vl_computation_table_2.php](../../../database/migrations/2021_06_25_231608_sl_vl_computation_table_2.php)

- **create** `table_2`: `(implicit/multiple columns)`: id; `number_of_days`: integer; `vl_earned`: double(12,3); `sl_earned`: double(12,3); `(implicit/multiple columns)`: timestamps.

## 2021_06_25_231711_sl_vl_computation_table_3.php

[database/migrations/2021_06_25_231711_sl_vl_computation_table_3.php](../../../database/migrations/2021_06_25_231711_sl_vl_computation_table_3.php)

- **create** `table_3`: `(implicit/multiple columns)`: id; `days_present`: double(12,2); `days_on_leave_wo_pay`: double(12,2); `leave_credits_earned`: double(12,3); `(implicit/multiple columns)`: timestamps.

## 2021_06_25_231910_sl_vl_computation_table_4.php

[database/migrations/2021_06_25_231910_sl_vl_computation_table_4.php](../../../database/migrations/2021_06_25_231910_sl_vl_computation_table_4.php)

- **create** `table_4`: `(implicit/multiple columns)`: id; `minutes`: integer; `equiv_day`: double(12,3); `(implicit/multiple columns)`: timestamps.

## 2021_06_26_002040_salary_grade_table.php

[database/migrations/2021_06_26_002040_salary_grade_table.php](../../../database/migrations/2021_06_26_002040_salary_grade_table.php)

- **create** `salary_grade`: `(implicit/multiple columns)`: id; `tranche`: string; `salary_grade`: integer; `step_1`: integer; `step_2`: integer; `step_3`: integer; `step_4`: integer; `step_5`: integer; `step_6`: integer; `step_7`: integer; `step_8`: integer; `(implicit/multiple columns)`: timestamps.

## 2021_06_26_083833_create__c_t_o_dates_table.php

[database/migrations/2021_06_26_083833_create__c_t_o_dates_table.php](../../../database/migrations/2021_06_26_083833_create__c_t_o_dates_table.php)

- **create** `c_t_o_dates`: `(implicit/multiple columns)`: id; `cto_id`: integer [nullable]; `cto_date`: date [nullable]; `hours`: integer [nullable]; `overtime_date_id`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2021_07_06_215951_user_staff_table.php

[database/migrations/2021_07_06_215951_user_staff_table.php](../../../database/migrations/2021_07_06_215951_user_staff_table.php)

- **table** `users`: `id_no`: string [after, nullable]; `nickname`: string [after, nullable]; `birthday`: date [after, nullable]; `bp_no`: string [after, nullable]; `pag_ibig`: string [after, nullable]; `tin_no`: string [after, nullable]; `philhealth`: string [after, nullable]; `blood_type`: string [after, nullable]; `home_address`: string(500) [after, nullable]; `emergency_address`: string(500) [after, nullable]; `contact_no`: string [after, nullable]; `contact_person`: string [after, nullable]; `contact_person_no`: string [after, nullable]; `signature`: string [after, nullable].

## 2021_07_10_051135_user_column_archive.php

[database/migrations/2021_07_10_051135_user_column_archive.php](../../../database/migrations/2021_07_10_051135_user_column_archive.php)

- **table** `users`: `isArchived`: string [default, nullable]; `img`: string [after, nullable].

## 2021_07_13_211058_payroll_tbl.php

[database/migrations/2021_07_13_211058_payroll_tbl.php](../../../database/migrations/2021_07_13_211058_payroll_tbl.php)

- **create** `payroll`: `(implicit/multiple columns)`: id; `payroll_type`: string; `payroll_no`: string [nullable]; `fund_cluster`: string; `date_from`: date; `date_to`: date; `user_id`: integer; `status`: string; `(implicit/multiple columns)`: timestamps.

## 2021_07_13_213737_payroll_staff_tbl.php

[database/migrations/2021_07_13_213737_payroll_staff_tbl.php](../../../database/migrations/2021_07_13_213737_payroll_staff_tbl.php)

- **create** `payroll_staff`: `(implicit/multiple columns)`: id; `payroll_id`: integer; `user_id`: integer; `salary_regular`: double(12,2) [default, nullable]; `salary_contractual`: double(12,2) [default, nullable]; `pera`: double(12,2) [default, nullable]; `rata`: double(12,2) [default, nullable]; `tax`: double(12,2) [default, nullable]; `rlip`: double(12,2) [default, nullable]; `philhealth`: double(12,2) [default, nullable]; `hdmf`: double(12,2) [default, nullable]; `hdmf_mp2`: double(12,2) [default, nullable]; `gsis_opt_policy`: double(12,2) [default, nullable]; `assert`: double(12,2) [default, nullable]; `conso_loan`: double(12,2) [default, nullable]; `regular_pol_loan`: double(12,2) [default, nullable]; `opt_pol_loan`: double(12,2) [default, nullable]; `gfal`: double(12,2) [default, nullable]; `emergency_loan`: double(12,2) [default, nullable]; `gsis_educ_loan`: double(12,2) [default, nullable]; `lowcos_house_loan`: double(12,2) [default, nullable]; `cpl`: double(12,2) [default, nullable]; `mpl`: double(12,2) [default, nullable]; `mult_purp_loan`: double(12,2) [default, nullable]; `calamity_loan`: double(12,2) [default, nullable]; `live_program`: double(12,2) [default, nullable]; `disallowance`: double(12,2) [default, nullable]; `gross`: double(12,2) [default, nullable]; `total_deduction`: double(12,2) [default, nullable]; `net`: double(12,2) [default, nullable]; `other_compensation_name`: double(12,2) [default, nullable]; `other_compensation_amount`: double(12,2) [default, nullable]; `other_deduction_name`: double(12,2) [default, nullable]; `other_deduction_amount`: double(12,2) [default, nullable]; `(implicit/multiple columns)`: timestamps.

## 2021_07_18_171610_add_amend_reason.php

[database/migrations/2021_07_18_171610_add_amend_reason.php](../../../database/migrations/2021_07_18_171610_add_amend_reason.php)

- **table** `official_businesses`: `reason`: string [after, nullable].

## 2021_07_24_175610_default_step_increment.php

[database/migrations/2021_07_24_175610_default_step_increment.php](../../../database/migrations/2021_07_24_175610_default_step_increment.php)

- **table** `users`: `step_increment`: integer [default, change].

## 2021_07_25_132126_create_ot_hours_rendered.php

[database/migrations/2021_07_25_132126_create_ot_hours_rendered.php](../../../database/migrations/2021_07_25_132126_create_ot_hours_rendered.php)

- **table** `dtr`: `ot_hours_rendered`: decimal(12,2) [after, nullable]; `coc`: decimal(12,3) [after, nullable].

## 2021_07_28_110720_add_remarks_field.php

[database/migrations/2021_07_28_110720_add_remarks_field.php](../../../database/migrations/2021_07_28_110720_add_remarks_field.php)

- **table** `statuses`: `remarks`: string [after, nullable].

## 2021_08_02_140159_payroll_phic.php

[database/migrations/2021_08_02_140159_payroll_phic.php](../../../database/migrations/2021_08_02_140159_payroll_phic.php)

- **table** `payroll_staff`: `phic`: double(12,2) [after, nullable]; `periodic_salary`: double(12,2) [after, nullable].

## 2021_08_03_223233_bonuses_field.php

[database/migrations/2021_08_03_223233_bonuses_field.php](../../../database/migrations/2021_08_03_223233_bonuses_field.php)

- **table** `payroll_staff`: `bonuses`: double(12,2) [after, nullable].

## 2021_08_04_170203_positions_plantilla_code.php

[database/migrations/2021_08_04_170203_positions_plantilla_code.php](../../../database/migrations/2021_08_04_170203_positions_plantilla_code.php)

- **table** `positions`: `position_code`: string [after, nullable].

## 2021_08_06_214955_create_at_date_field.php

[database/migrations/2021_08_06_214955_create_at_date_field.php](../../../database/migrations/2021_08_06_214955_create_at_date_field.php)

- **table** `dtr`: `created_at_date`: date [nullable].

## 2021_08_11_054914_user_sex.php

[database/migrations/2021_08_11_054914_user_sex.php](../../../database/migrations/2021_08_11_054914_user_sex.php)

- **table** `users`: `sex`: string [after, nullable].

## 2021_08_15_011816_position_group.php

[database/migrations/2021_08_15_011816_position_group.php](../../../database/migrations/2021_08_15_011816_position_group.php)

- **table** `positions`: `group`: integer.

## 2021_08_22_000410_create_leave_credit_minute_bases_table.php

[database/migrations/2021_08_22_000410_create_leave_credit_minute_bases_table.php](../../../database/migrations/2021_08_22_000410_create_leave_credit_minute_bases_table.php)

- **create** `leave_credit_minute_bases`: `(implicit/multiple columns)`: id; `minutes`: integer; `equiv_day`: decimal; `(implicit/multiple columns)`: timestamps.

## 2021_09_12_165927_create_ssis_table.php

[database/migrations/2021_09_12_165927_create_ssis_table.php](../../../database/migrations/2021_09_12_165927_create_ssis_table.php)

- **create** `ssis`: `(implicit/multiple columns)`: id; `url`: string; `title`: string [nullable]; `description`: string [nullable]; `img`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2021_10_04_161446_for_disapproval.php

[database/migrations/2021_10_04_161446_for_disapproval.php](../../../database/migrations/2021_10_04_161446_for_disapproval.php)

- **table** `leaves`: `for_disapproval`: integer [default].

## 2021_10_17_012400_create_data_sheets_table.php

[database/migrations/2021_10_17_012400_create_data_sheets_table.php](../../../database/migrations/2021_10_17_012400_create_data_sheets_table.php)

- **create** `data_sheets`: `(implicit/multiple columns)`: id; `user_id`: integer; `name`: string; `attachment`: string; `(implicit/multiple columns)`: timestamps.

## 2021_10_27_154436_position_recruitment_status.php

[database/migrations/2021_10_27_154436_position_recruitment_status.php](../../../database/migrations/2021_10_27_154436_position_recruitment_status.php)

- **table** `positions`: `status`: string [nullable].

## 2021_10_28_221535_recruitment_categories.php

[database/migrations/2021_10_28_221535_recruitment_categories.php](../../../database/migrations/2021_10_28_221535_recruitment_categories.php)

- **create** `r_categories`: `(implicit/multiple columns)`: id; `category`: string; `question_type`: string; `img`: string; `time_limit`: integer; `(implicit/multiple columns)`: timestamps.

## 2021_10_28_223416_recruitment_questions.php

[database/migrations/2021_10_28_223416_recruitment_questions.php](../../../database/migrations/2021_10_28_223416_recruitment_questions.php)

- **create** `r_questions`: `(implicit/multiple columns)`: id; `category_id`: integer; `question`: string(2500); `img`: string; `(implicit/multiple columns)`: timestamps.

## 2021_10_28_232202_recruitment_questions_choices.php

[database/migrations/2021_10_28_232202_recruitment_questions_choices.php](../../../database/migrations/2021_10_28_232202_recruitment_questions_choices.php)

- **create** `r_choices`: `(implicit/multiple columns)`: id; `question_id`: integer; `answer`: string; `img`: string; `(implicit/multiple columns)`: timestamps.

## 2021_10_28_232831_recruitment_position_category.php

[database/migrations/2021_10_28_232831_recruitment_position_category.php](../../../database/migrations/2021_10_28_232831_recruitment_position_category.php)

- **create** `r_position_category`: `(implicit/multiple columns)`: id; `position_id`: integer; `category_id`: integer; `(implicit/multiple columns)`: timestamps.

## 2021_10_28_232846_recruitment_position_applicant.php

[database/migrations/2021_10_28_232846_recruitment_position_applicant.php](../../../database/migrations/2021_10_28_232846_recruitment_position_applicant.php)

- **create** `r_position_applicant`: `(implicit/multiple columns)`: id; `position_id`: integer; `user_id`: integer; `(implicit/multiple columns)`: timestamps.

## 2021_11_02_065955_exam_categories_modify.php

[database/migrations/2021_11_02_065955_exam_categories_modify.php](../../../database/migrations/2021_11_02_065955_exam_categories_modify.php)

- **table** `r_categories`: `(implicit/multiple columns)`: dropColumn.

Includes raw SQL or rename/drop columns; read the migration before mapping final fields.

## 2021_11_02_070124_exam_questions_modify.php

[database/migrations/2021_11_02_070124_exam_questions_modify.php](../../../database/migrations/2021_11_02_070124_exam_questions_modify.php)

- **table** `r_questions`: `question_type`: string [after].

## 2021_11_06_202157_create_uploads_table.php

[database/migrations/2021_11_06_202157_create_uploads_table.php](../../../database/migrations/2021_11_06_202157_create_uploads_table.php)

- **create** `uploads`: `(implicit/multiple columns)`: id; `emp_id`: integer; `type`: string; `file`: string; `remarks`: string; `year`: integer; `(implicit/multiple columns)`: timestamps.

## 2021_11_25_151509_r_position_applicant_status.php

[database/migrations/2021_11_25_151509_r_position_applicant_status.php](../../../database/migrations/2021_11_25_151509_r_position_applicant_status.php)

- **table** `r_position_applicant`: `status`: string [after]; `remark`: string [after, nullable].

## 2021_12_01_133957_application_toggle.php

[database/migrations/2021_12_01_133957_application_toggle.php](../../../database/migrations/2021_12_01_133957_application_toggle.php)

- **table** `r_position_applicant`: `allow_test`: integer [default, after].

## 2021_12_01_195824_r_exam_timer.php

[database/migrations/2021_12_01_195824_r_exam_timer.php](../../../database/migrations/2021_12_01_195824_r_exam_timer.php)

- **create** `r_exam_timer`: `(implicit/multiple columns)`: id; `user_id`: integer; `category_id`: integer; `time_end`: dateTime; `(implicit/multiple columns)`: timestamps.

## 2021_12_01_200506_r_exam_answers.php

[database/migrations/2021_12_01_200506_r_exam_answers.php](../../../database/migrations/2021_12_01_200506_r_exam_answers.php)

- **create** `r_exam_answers`: `(implicit/multiple columns)`: id; `user_id`: integer; `question_id`: integer; `answer`: string(3000); `file_attachment`: string; `(implicit/multiple columns)`: timestamps.

## 2021_12_02_160642_is_submitted_r_exam_timer.php

[database/migrations/2021_12_02_160642_is_submitted_r_exam_timer.php](../../../database/migrations/2021_12_02_160642_is_submitted_r_exam_timer.php)

- **table** `r_exam_timer`: `is_submitted`: string [default, after].

## 2021_12_02_162523_r_exam_answers_edit.php

[database/migrations/2021_12_02_162523_r_exam_answers_edit.php](../../../database/migrations/2021_12_02_162523_r_exam_answers_edit.php)

- **table** `r_exam_answers`: `user_id`: renameColumn.

Includes raw SQL or rename/drop columns; read the migration before mapping final fields.

## 2021_12_11_005953_r_exam_answer_user_id.php

[database/migrations/2021_12_11_005953_r_exam_answer_user_id.php](../../../database/migrations/2021_12_11_005953_r_exam_answer_user_id.php)

- **table** `r_exam_answers`: `application_id`: renameColumn; `file_attachment`: dropColumn.

Includes raw SQL or rename/drop columns; read the migration before mapping final fields.

## 2021_12_16_134329_make_answer_nullable.php

[database/migrations/2021_12_16_134329_make_answer_nullable.php](../../../database/migrations/2021_12_16_134329_make_answer_nullable.php)

- **table** `r_exam_answers`: `answer`: string [nullable, change].

## 2021_12_21_125545_exam_type_categories.php

[database/migrations/2021_12_21_125545_exam_type_categories.php](../../../database/migrations/2021_12_21_125545_exam_type_categories.php)

- **table** `r_categories`: `exam_type`: string [after, nullable].

## 2021_12_22_010558_create_recognition_rewards_table.php

[database/migrations/2021_12_22_010558_create_recognition_rewards_table.php](../../../database/migrations/2021_12_22_010558_create_recognition_rewards_table.php)

- **create** `recognition_rewards`: `(implicit/multiple columns)`: id; `recognition_name`: string; `start`: date; `end`: date; `recognition_date`: date; `type`: string; `nominees`: text; `winners`: text [nullable]; `(implicit/multiple columns)`: timestamps.

## 2021_12_22_102154_position_evaluators_tbl.php

[database/migrations/2021_12_22_102154_position_evaluators_tbl.php](../../../database/migrations/2021_12_22_102154_position_evaluators_tbl.php)

- **create** `position_evaluators`: `(implicit/multiple columns)`: id; `user_id`: integer; `position_id`: integer; `role`: string; `(implicit/multiple columns)`: timestamps.

## 2021_12_26_031423_r_eval_docs.php

[database/migrations/2021_12_26_031423_r_eval_docs.php](../../../database/migrations/2021_12_26_031423_r_eval_docs.php)

- **create** `r_eval_docs`: `(implicit/multiple columns)`: id; `application_id`: integer; `type`: string; `file`: string; `(implicit/multiple columns)`: timestamps.

## 2021_12_27_022329_create_recognition_award_votes_table.php

[database/migrations/2021_12_27_022329_create_recognition_award_votes_table.php](../../../database/migrations/2021_12_27_022329_create_recognition_award_votes_table.php)

- **create** `recognition_award_votes`: `(implicit/multiple columns)`: id; `recognition_award_id`: integer; `nominees_id`: integer; `voter_id`: integer; `(implicit/multiple columns)`: timestamps.

## 2022_04_08_173814_user_personal.php

[database/migrations/2022_04_08_173814_user_personal.php](../../../database/migrations/2022_04_08_173814_user_personal.php)

- **create** `users_personal`: `(implicit/multiple columns)`: id; `user_id`: integer; `firstname`: string; `middlename`: string [nullable]; `lastname`: string; `extension`: string [nullable]; `birthday`: date; `place_of_birth`: string; `citizenship`: string; `citizenship_by`: string; `citizenship_country`: string [nullable]; `sex`: string; `civil_status`: string; `civil_status_details`: string [nullable]; `height`: string [nullable]; `weight`: string [nullable]; `blood_type`: string [nullable]; `gsis`: string [nullable]; `pagibig`: string [nullable]; `philhealth`: string [nullable]; `sss`: string [nullable]; `tin`: string [nullable]; `employee_no`: string [nullable]; `telephone_no`: string [nullable]; `mobile_no`: string [nullable]; `res_house`: string(150) [nullable]; `res_street`: string(150) [nullable]; `res_subdivision`: string(150) [nullable]; `res_baranggay`: string(150) [nullable]; `res_city`: string(150) [nullable]; `res_province`: string(150) [nullable]; `res_zip_code`: string(150) [nullable]; `perm_house`: string(150) [nullable]; `perm_street`: string(150) [nullable]; `perm_subdivision`: string(150) [nullable]; `perm_baranggay`: string(150) [nullable]; `perm_city`: string(150) [nullable]; `perm_province`: string(150) [nullable]; `perm_zip_code`: string(150) [nullable]; `home_address`: string(1000); `contact_person`: string; `contact_person_no`: string; `emergency_address`: string(1000); `(implicit/multiple columns)`: timestamps.

## 2022_05_02_135533_dtr_rfid.php

[database/migrations/2022_05_02_135533_dtr_rfid.php](../../../database/migrations/2022_05_02_135533_dtr_rfid.php)

- **create** `dtr_time_log`: `(implicit/multiple columns)`: id; `anviz_log_id`: integer; `user_atms_id`: integer; `time_log`: datetime; `device`: string; `record_type`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2022_05_23_111800_is_analyzed.php

[database/migrations/2022_05_23_111800_is_analyzed.php](../../../database/migrations/2022_05_23_111800_is_analyzed.php)

- **table** `dtr`: `isAnalyzed`: string [nullable].

## 2022_05_24_104013_users_fam_bg.php

[database/migrations/2022_05_24_104013_users_fam_bg.php](../../../database/migrations/2022_05_24_104013_users_fam_bg.php)

- **create** `users_fam`: `(implicit/multiple columns)`: id; `user_id`: integer [nullable]; `spouse_fname`: string [nullable]; `spouse_lname`: string [nullable]; `spouse_mname`: string [nullable]; `spouse_ext`: string [nullable]; `spouse_occupation`: string [nullable]; `spouse_employer`: string [nullable]; `spouse_bus_add`: string [nullable]; `spouse_contact`: string [nullable]; `father_fname`: string [nullable]; `father_lname`: string [nullable]; `father_mname`: string [nullable]; `father_ext`: string [nullable]; `mother_fname`: string [nullable]; `mother_lname`: string [nullable]; `mother_mname`: string [nullable]; `children`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2022_05_25_100402_user_educ_table.php

[database/migrations/2022_05_25_100402_user_educ_table.php](../../../database/migrations/2022_05_25_100402_user_educ_table.php)

- **create** `users_educ`: `(implicit/multiple columns)`: id; `user_id`: integer; `educ_level`: string; `school`: string; `course`: string; `date_from`: date; `date_to`: date; `highest_level`: string; `year_graduated`: integer; `honors`: string; `(implicit/multiple columns)`: timestamps.

## 2022_05_26_084439_users_eligibility_tbl.php

[database/migrations/2022_05_26_084439_users_eligibility_tbl.php](../../../database/migrations/2022_05_26_084439_users_eligibility_tbl.php)

- **create** `users_eligibility`: `(implicit/multiple columns)`: id; `user_id`: integer; `eligibility`: string; `rating`: string [nullable]; `exam_date`: date; `exam_place`: string; `license_no`: string [nullable]; `validity_date`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2022_05_26_095940_user_workex_tbl.php

[database/migrations/2022_05_26_095940_user_workex_tbl.php](../../../database/migrations/2022_05_26_095940_user_workex_tbl.php)

- **create** `users_workex`: `(implicit/multiple columns)`: id; `user_id`: integer; `date_from`: date; `date_to`: date; `position`: string; `department`: string; `salary`: string; `step_increment`: string; `appointment_status`: string; `govt_service`: string; `(implicit/multiple columns)`: timestamps.

## 2022_05_27_094159_vol_work_tbl.php

[database/migrations/2022_05_27_094159_vol_work_tbl.php](../../../database/migrations/2022_05_27_094159_vol_work_tbl.php)

- **create** `users_vol_work`: `(implicit/multiple columns)`: id; `user_id`: integer; `org_name_address`: string; `date_from`: date; `date_to`: date; `hours`: string; `position`: string; `(implicit/multiple columns)`: timestamps.

## 2022_05_27_101547_users_ld.php

[database/migrations/2022_05_27_101547_users_ld.php](../../../database/migrations/2022_05_27_101547_users_ld.php)

- **create** `users_ld`: `(implicit/multiple columns)`: id; `user_id`: integer; `ld_title`: string; `date_from`: string; `date_to`: string; `hours`: string; `ld_type`: string; `conducted_by`: string; `(implicit/multiple columns)`: timestamps.

## 2022_06_20_200201_ssi_events.php

[database/migrations/2022_06_20_200201_ssi_events.php](../../../database/migrations/2022_06_20_200201_ssi_events.php)

- **create** `ssi_events`: `(implicit/multiple columns)`: id; `schedule_type`: string; `event_type`: string; `title`: string; `description`: string(3000); `date_start`: datetime [nullable]; `date_end`: datetime [nullable]; `(implicit/multiple columns)`: timestamps.

## 2022_06_27_144440_ssi_announcements.php

[database/migrations/2022_06_27_144440_ssi_announcements.php](../../../database/migrations/2022_06_27_144440_ssi_announcements.php)

- **create** `ssi_announcements`: `(implicit/multiple columns)`: id; `user_id`: integer; `title`: string; `announcement`: string(5000); `(implicit/multiple columns)`: timestamps.

## 2022_07_05_140834_division_field.php

[database/migrations/2022_07_05_140834_division_field.php](../../../database/migrations/2022_07_05_140834_division_field.php)

- **table** `ssi_events`: `division_id`: integer [after].

## 2022_07_05_140930_division_accronym.php

[database/migrations/2022_07_05_140930_division_accronym.php](../../../database/migrations/2022_07_05_140930_division_accronym.php)

- **table** `divisions`: `acronym`: string [nullable, after].

## 2022_07_05_144634_involved_pax.php

[database/migrations/2022_07_05_144634_involved_pax.php](../../../database/migrations/2022_07_05_144634_involved_pax.php)

- **table** `ssi_events`: `involved_pax`: string [nullable, after].

## 2022_08_04_140348_announcement_file_upload.php

[database/migrations/2022_08_04_140348_announcement_file_upload.php](../../../database/migrations/2022_08_04_140348_announcement_file_upload.php)

- **table** `ssi_announcements`: `upload`: string [nullable].

## 2022_09_28_140132_leave_type_table.php

[database/migrations/2022_09_28_140132_leave_type_table.php](../../../database/migrations/2022_09_28_140132_leave_type_table.php)

- **create** `leave_types`: `(implicit/multiple columns)`: id; `leave_type`: string; `acronym`: string; `accumulation`: string [nullable]; `get_credit_from_id`: integer [nullable]; `(implicit/multiple columns)`: timestamps.

## 2022_09_28_151456_spl_remark_to_study_remark.php

[database/migrations/2022_09_28_151456_spl_remark_to_study_remark.php](../../../database/migrations/2022_09_28_151456_spl_remark_to_study_remark.php)

- **table** `leaves`: `spl_remark`: renameColumn; `status`: dropColumn.

Includes raw SQL or rename/drop columns; read the migration before mapping final fields.

## 2022_09_29_115816_date_type.php

[database/migrations/2022_09_29_115816_date_type.php](../../../database/migrations/2022_09_29_115816_date_type.php)

- **table** `leaves`: `date_type`: string [nullable].

## 2022_09_29_163341_leave_table_remodel.php

[database/migrations/2022_09_29_163341_leave_table_remodel.php](../../../database/migrations/2022_09_29_163341_leave_table_remodel.php)

- **table** `leaves`: `vl_type`: dropColumn; `vl_type_remark`: dropColumn; `vl_spent`: dropColumn; `vl_spent_remark`: dropColumn; `sl_type`: dropColumn; `sl_type_remark`: dropColumn; `study_remark`: dropColumn; `num_of_days`: integer; `disapproval_due_to`: dropColumn; `for_disapproval`: dropColumn.

Includes raw SQL or rename/drop columns; read the migration before mapping final fields.

## 2022_09_29_170718_details_of_leave.php

[database/migrations/2022_09_29_170718_details_of_leave.php](../../../database/migrations/2022_09_29_170718_details_of_leave.php)

- **table** `leaves`: `details_of_leave`: integer [nullable, after]; `details_specify`: string [nullable, after].

## 2022_10_12_171752_f_l_for_disapproval.php

[database/migrations/2022_10_12_171752_f_l_for_disapproval.php](../../../database/migrations/2022_10_12_171752_f_l_for_disapproval.php)

- **table** `leaves`: `fl_for_disapproval`: char [default].

## 2022_12_07_111625_solo_parent_field.php

[database/migrations/2022_12_07_111625_solo_parent_field.php](../../../database/migrations/2022_12_07_111625_solo_parent_field.php)

- **table** `users`: `solo_parent`: char [default, after, nullable].

## 2022_12_07_135749_return_message.php

[database/migrations/2022_12_07_135749_return_message.php](../../../database/migrations/2022_12_07_135749_return_message.php)

- **table** `leaves`: `return_message`: string [nullable].

## 2022_12_07_153210_other_info_tbl.php

[database/migrations/2022_12_07_153210_other_info_tbl.php](../../../database/migrations/2022_12_07_153210_other_info_tbl.php)

- **create** `users_other_info`: `(implicit/multiple columns)`: id; `user_id`: integer; `special_skills_hobbies`: string; `non_academic_distinctions_recognition`: date; `membership_association_organization`: date; `(implicit/multiple columns)`: timestamps.

## 2022_12_07_171147_modify_other_info_tbl.php

[database/migrations/2022_12_07_171147_modify_other_info_tbl.php](../../../database/migrations/2022_12_07_171147_modify_other_info_tbl.php)

- **table** `users_other_info`: `special_skills_hobbies`: string [nullable, change]; `non_academic_distinctions_recognition`: string [nullable, change]; `membership_association_organization`: string [nullable, change].

## 2022_12_09_113704_gov_dec_tbl.php

[database/migrations/2022_12_09_113704_gov_dec_tbl.php](../../../database/migrations/2022_12_09_113704_gov_dec_tbl.php)

- **create** `users_gov_dec`: `(implicit/multiple columns)`: id; `user_id`: integer; `34_a`: string; `34_a2`: string [nullable]; `35_a`: string; `35_a2`: string [nullable]; `35_b`: string; `35_b2`: string [nullable]; `35_b3`: string [nullable]; `36_a`: string; `36_a2`: string [nullable]; `37_a`: string; `37_a2`: string [nullable]; `38_a`: string; `38_a2`: string [nullable]; `38_b`: string; `38_b2`: string [nullable]; `39_a`: string; `39_a2`: string [nullable]; `40_a`: string; `40_a2`: string [nullable]; `40_b`: string; `40_b2`: string [nullable]; `40_c`: string; `40_c2`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2022_12_22_174832_char_ref_tbl.php

[database/migrations/2022_12_22_174832_char_ref_tbl.php](../../../database/migrations/2022_12_22_174832_char_ref_tbl.php)

- **create** `users_char_ref`: `(implicit/multiple columns)`: id; `user_id`: integer; `name_1`: string; `add_1`: string; `contact_1`: string; `name_2`: string; `add_2`: string; `contact_2`: string; `name_3`: string; `add_3`: string; `contact_3`: string; `(implicit/multiple columns)`: timestamps.

## 2022_12_28_115245_gov_id_tbl.php

[database/migrations/2022_12_28_115245_gov_id_tbl.php](../../../database/migrations/2022_12_28_115245_gov_id_tbl.php)

- **create** `users_gov_id`: `(implicit/multiple columns)`: id; `user_id`: integer; `gov_id`: string; `id_no`: string; `date_issued`: date; `place_issued`: string; `(implicit/multiple columns)`: timestamps.

## 2023_01_16_153929_leaves_leavelog_id.php

[database/migrations/2023_01_16_153929_leaves_leavelog_id.php](../../../database/migrations/2023_01_16_153929_leaves_leavelog_id.php)

- **table** `leaves`: `leave_log_id`: integer [nullable].

## 2023_01_23_122505_int_to_varchar.php

[database/migrations/2023_01_23_122505_int_to_varchar.php](../../../database/migrations/2023_01_23_122505_int_to_varchar.php)

- **table** `dtr_time_log`: `user_atms_id`: string [change].

## 2023_02_14_154112_scope_date.php

[database/migrations/2023_02_14_154112_scope_date.php](../../../database/migrations/2023_02_14_154112_scope_date.php)

- **table** `leave_log`: `scope_date`: date [nullable].

## 2023_02_17_105530_monthly_attendace_report_remarks_fc.php

[database/migrations/2023_02_17_105530_monthly_attendace_report_remarks_fc.php](../../../database/migrations/2023_02_17_105530_monthly_attendace_report_remarks_fc.php)

- **create** `dtr_remarks`: `(implicit/multiple columns)`: id; `user_id`: integer; `fc_absence`: integer [default]; `remarks`: string(1000) [nullable]; `coverage`: date; `(implicit/multiple columns)`: timestamps.

## 2023_02_17_164535_termination_date.php

[database/migrations/2023_02_17_164535_termination_date.php](../../../database/migrations/2023_02_17_164535_termination_date.php)

- **table** `users`: `termination_date`: date [nullable].

## 2023_02_22_155556_tardy_type_leavelog.php

[database/migrations/2023_02_22_155556_tardy_type_leavelog.php](../../../database/migrations/2023_02_22_155556_tardy_type_leavelog.php)

- **table** `leave_log`: `tardy_type`: string [nullable].

## 2023_03_01_095514_modify_not_nullable.php

[database/migrations/2023_03_01_095514_modify_not_nullable.php](../../../database/migrations/2023_03_01_095514_modify_not_nullable.php)

- **table** `users_fam`: `user_id`: integer [change]; `spouse_fname`: string [change]; `spouse_lname`: string [change]; `spouse_mname`: string [change]; `spouse_ext`: string [change]; `spouse_occupation`: string [change]; `spouse_employer`: string [change]; `spouse_bus_add`: string [change]; `spouse_contact`: string [change]; `father_fname`: string [change]; `father_lname`: string [change]; `father_mname`: string [change]; `father_ext`: string [change]; `mother_fname`: string [change]; `mother_lname`: string [change]; `mother_mname`: string [change]; `children`: string [change].

## 2023_03_01_101800_modify_not_nullable_2.php

[database/migrations/2023_03_01_101800_modify_not_nullable_2.php](../../../database/migrations/2023_03_01_101800_modify_not_nullable_2.php)

- **table** `users_fam`: `spouse_fname`: string [nullable, change]; `spouse_lname`: string [nullable, change]; `spouse_mname`: string [nullable, change]; `spouse_ext`: string [nullable, change]; `spouse_occupation`: string [nullable, change]; `spouse_employer`: string [nullable, change]; `spouse_bus_add`: string [nullable, change]; `spouse_contact`: string [nullable, change]; `father_fname`: string [nullable, change]; `father_lname`: string [nullable, change]; `father_mname`: string [nullable, change]; `father_ext`: string [nullable, change]; `mother_fname`: string [nullable, change]; `mother_lname`: string [nullable, change]; `mother_mname`: string [nullable, change].

## 2023_03_01_170307_modify_users_educ.php

[database/migrations/2023_03_01_170307_modify_users_educ.php](../../../database/migrations/2023_03_01_170307_modify_users_educ.php)

- **table** `users_educ`: `date_from`: integer [change]; `date_to`: integer [change].

## 2023_05_09_104807_users_personal_nullable.php

[database/migrations/2023_05_09_104807_users_personal_nullable.php](../../../database/migrations/2023_05_09_104807_users_personal_nullable.php)

- **table** `users_personal`: `place_of_birth`: string [nullable, change]; `citizenship`: string [nullable, change]; `citizenship_by`: string [nullable, change]; `sex`: string [nullable, change]; `civil_status`: string [nullable, change].

## 2023_06_14_100952_add_workweek_column.php

[database/migrations/2023_06_14_100952_add_workweek_column.php](../../../database/migrations/2023_06_14_100952_add_workweek_column.php)

- **table** `users`: `workweek`: integer [nullable].

## 2023_06_22_141526_bdayfile_tbl.php

[database/migrations/2023_06_22_141526_bdayfile_tbl.php](../../../database/migrations/2023_06_22_141526_bdayfile_tbl.php)

- **create** `bday_file`: `(implicit/multiple columns)`: id; `file`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2023_06_22_172714_bdayfile_edit1_tbl.php

[database/migrations/2023_06_22_172714_bdayfile_edit1_tbl.php](../../../database/migrations/2023_06_22_172714_bdayfile_edit1_tbl.php)

- **table** `bday_file`: `file`: dropColumn.

Includes raw SQL or rename/drop columns; read the migration before mapping final fields.

## 2023_06_22_174939_bdayfile_edit2_tbl.php

[database/migrations/2023_06_22_174939_bdayfile_edit2_tbl.php](../../../database/migrations/2023_06_22_174939_bdayfile_edit2_tbl.php)

- **table** `bday_file`: `file`: string [after].

## 2023_09_18_132000_atms_id_index.php

[database/migrations/2023_09_18_132000_atms_id_index.php](../../../database/migrations/2023_09_18_132000_atms_id_index.php)

- **table** `users`: `atms_id`: index.

## 2023_09_18_132154_dtrtimelog_index.php

[database/migrations/2023_09_18_132154_dtrtimelog_index.php](../../../database/migrations/2023_09_18_132154_dtrtimelog_index.php)

- **table** `dtr_time_log`: `user_atms_id`: index.

## 2023_09_18_142141_id_index.php

[database/migrations/2023_09_18_142141_id_index.php](../../../database/migrations/2023_09_18_142141_id_index.php)

- **table** `users`: `id`: index.

## 2023_10_04_190730_add_file_column.php

[database/migrations/2023_10_04_190730_add_file_column.php](../../../database/migrations/2023_10_04_190730_add_file_column.php)

- **table** `users_ld`: `file`: string [after, nullable].

## 2023_11_08_190331_add_file_id.php

[database/migrations/2023_11_08_190331_add_file_id.php](../../../database/migrations/2023_11_08_190331_add_file_id.php)

- **table** `users_gov_id`: `file`: string [after, nullable].

## 2023_11_20_135113_modify_hours_rendered.php

[database/migrations/2023_11_20_135113_modify_hours_rendered.php](../../../database/migrations/2023_11_20_135113_modify_hours_rendered.php)

- **table** `dtr`: `hours_rendered`: decimal(8,4) [change, nullable].

## 2023_11_22_150505_add_emps_column.php

[database/migrations/2023_11_22_150505_add_emps_column.php](../../../database/migrations/2023_11_22_150505_add_emps_column.php)

- **table** `holidays`: `employees`: string [after, nullable].

## 2024_02_05_105103_change_type_date_to.php

[database/migrations/2024_02_05_105103_change_type_date_to.php](../../../database/migrations/2024_02_05_105103_change_type_date_to.php)

- **table** `users_workex`: `date_to`: string [change].

## 2024_02_06_092040_change_type_ob.php

[database/migrations/2024_02_06_092040_change_type_ob.php](../../../database/migrations/2024_02_06_092040_change_type_ob.php)

- **table** `official_businesses`: `departure_time`: string [nullable, change]; `return_time`: string [nullable, change].

## 2024_02_28_193122_change_type_educ.php

[database/migrations/2024_02_28_193122_change_type_educ.php](../../../database/migrations/2024_02_28_193122_change_type_educ.php)

- **table** `users_educ`: `date_to`: string [change]; `year_graduated`: string [change].

## 2024_02_29_171201_change_type_date.php

[database/migrations/2024_02_29_171201_change_type_date.php](../../../database/migrations/2024_02_29_171201_change_type_date.php)

- **table** `users_vol_work`: `date_from`: date [nullable, change]; `date_to`: date [nullable, change].

## 2024_03_11_174804_change_vol_date_to.php

[database/migrations/2024_03_11_174804_change_vol_date_to.php](../../../database/migrations/2024_03_11_174804_change_vol_date_to.php)

- **table** `users_vol_work`: `date_to`: string [nullable, change].

## 2024_06_26_185219_l_n_d_matrix_tbl.php

[database/migrations/2024_06_26_185219_l_n_d_matrix_tbl.php](../../../database/migrations/2024_06_26_185219_l_n_d_matrix_tbl.php)

- **create** `users_ld_matrix`: `(implicit/multiple columns)`: id; `category`: string; `training_value`: string; `position_id`: integer; `(implicit/multiple columns)`: timestamps.

## 2024_06_26_185845_users_matrix_id.php

[database/migrations/2024_06_26_185845_users_matrix_id.php](../../../database/migrations/2024_06_26_185845_users_matrix_id.php)

- **table** `users_ld`: `matrix_id`: integer [nullable].

## 2024_07_09_143818_position_type.php

[database/migrations/2024_07_09_143818_position_type.php](../../../database/migrations/2024_07_09_143818_position_type.php)

- **table** `positions`: `plantilla`: string [default].

## 2024_07_10_175011_add_auto_approve.php

[database/migrations/2024_07_10_175011_add_auto_approve.php](../../../database/migrations/2024_07_10_175011_add_auto_approve.php)

- **table** `statuses`: `auto_approve`: string [after, default].

## 2024_08_21_134647_add_tardiness_field.php

[database/migrations/2024_08_21_134647_add_tardiness_field.php](../../../database/migrations/2024_08_21_134647_add_tardiness_field.php)

- **table** `dtr`: `late`: integer [after, nullable]; `undertime`: integer [after, nullable]; `work_sched`: integer [after, nullable].

## 2024_08_29_100653_matrix_budget.php

[database/migrations/2024_08_29_100653_matrix_budget.php](../../../database/migrations/2024_08_29_100653_matrix_budget.php)

- **create** `users_ld_matrix_budget`: `(implicit/multiple columns)`: id; `matrix_id`: integer; `year`: integer; `budget`: integer; `(implicit/multiple columns)`: timestamps.

## 2024_09_04_132035_add_positions_history_tbl.php

[database/migrations/2024_09_04_132035_add_positions_history_tbl.php](../../../database/migrations/2024_09_04_132035_add_positions_history_tbl.php)

- **create** `positions_history`: `(implicit/multiple columns)`: id; `position_id`: integer; `user_id`: integer; `hr_user_id`: integer; `(implicit/multiple columns)`: timestamps.

## 2024_09_05_165059_add_assume_date.php

[database/migrations/2024_09_05_165059_add_assume_date.php](../../../database/migrations/2024_09_05_165059_add_assume_date.php)

- **table** `positions_history`: `assume_date`: date [after, nullable].

## 2024_09_30_162411_add_time_to_holidays_table.php

[database/migrations/2024_09_30_162411_add_time_to_holidays_table.php](../../../database/migrations/2024_09_30_162411_add_time_to_holidays_table.php)

- **table** `holidays`: `time_suspended`: string [after, nullable].

## 2024_11_19_175334_user_idp_table.php

[database/migrations/2024_11_19_175334_user_idp_table.php](../../../database/migrations/2024_11_19_175334_user_idp_table.php)

- **create** `users_idp`: `(implicit/multiple columns)`: id; `idp_year`: string; `user_id`: integer; `curr_position`: string; `sg`: string; `yrs_position`: string [nullable]; `yrs_agency`: string [nullable]; `year1`: char [nullable]; `year2`: char [nullable]; `year3`: char [nullable]; `purpose`: integer; `indicate_purpose`: string [nullable]; `dc_approved`: integer [nullable]; `dc_approve_date`: date [nullable]; `hrdc_approved`: integer [nullable]; `hrdc_approve_date`: date [nullable]; `training`: json [nullable]; `(implicit/multiple columns)`: timestamps.

## 2024_11_26_194413_idp_submit_date.php

[database/migrations/2024_11_26_194413_idp_submit_date.php](../../../database/migrations/2024_11_26_194413_idp_submit_date.php)

- **table** `users_idp`: `submit_date`: date [nullable, after].

## 2024_11_27_145655_user_hrdc_setting.php

[database/migrations/2024_11_27_145655_user_hrdc_setting.php](../../../database/migrations/2024_11_27_145655_user_hrdc_setting.php)

- **table** `users`: `hrdc`: char [default, after].

## 2024_12_09_183730_matrix_budget_isfree.php

[database/migrations/2024_12_09_183730_matrix_budget_isfree.php](../../../database/migrations/2024_12_09_183730_matrix_budget_isfree.php)

- **table** `users_ld_matrix_budget`: `isFree`: char [default, after].

## 2024_12_26_163028_create_dtr_summary_table.php

[database/migrations/2024_12_26_163028_create_dtr_summary_table.php](../../../database/migrations/2024_12_26_163028_create_dtr_summary_table.php)

- **create** `dtr_summary`: `(implicit/multiple columns)`: id; `emp_id`: integer; `work_sched`: string [nullable]; `month_year`: string; `days_present`: integer [nullable]; `abscences`: integer [nullable]; `lates`: integer [nullable]; `undertimes`: integer [nullable]; `(implicit/multiple columns)`: timestamps.

## 2025_01_13_100348_change_num_days.php

[database/migrations/2025_01_13_100348_change_num_days.php](../../../database/migrations/2025_01_13_100348_change_num_days.php)

- **table** `leaves`: `num_of_days`: decimal(3,2) [nullable, change].

## 2025_01_22_184107_add_ob_type.php

[database/migrations/2025_01_22_184107_add_ob_type.php](../../../database/migrations/2025_01_22_184107_add_ob_type.php)

- **table** `official_businesses`: `ob_type`: integer [after, nullable].

## 2025_03_12_175358_add_ob_file.php

[database/migrations/2025_03_12_175358_add_ob_file.php](../../../database/migrations/2025_03_12_175358_add_ob_file.php)

- **table** `official_businesses`: `ob_file`: string [after, nullable].

## 2025_04_03_144117_change_num_days_type.php

[database/migrations/2025_04_03_144117_change_num_days_type.php](../../../database/migrations/2025_04_03_144117_change_num_days_type.php)

- **table** `leaves`: `days_with_pay`: decimal(6,3) [change]; `days_wo_pay`: decimal(6,3) [change]; `num_of_days`: string(5,2) [change].

## 2025_07_17_132210_create_coc_logs_table.php

[database/migrations/2025_07_17_132210_create_coc_logs_table.php](../../../database/migrations/2025_07_17_132210_create_coc_logs_table.php)

- **create** `coc_log`: `(implicit/multiple columns)`: id; `user_id`: integer; `credit_amount`: decimal(5,2) [nullable]; `total_credits`: decimal(5,2) [nullable]; `status`: string [nullable]; `scope_date`: date [nullable]; `(implicit/multiple columns)`: timestamps.

## 2025_07_28_172832_rename_to_remarks.php

[database/migrations/2025_07_28_172832_rename_to_remarks.php](../../../database/migrations/2025_07_28_172832_rename_to_remarks.php)

- **table** `c_t_o_dates`: `overtime_date_id`: renameColumn.

Includes raw SQL or rename/drop columns; read the migration before mapping final fields.

## 2025_07_30_151013_add_total_hours.php

[database/migrations/2025_07_30_151013_add_total_hours.php](../../../database/migrations/2025_07_30_151013_add_total_hours.php)

- **table** `c_t_o_s`: `total_hours`: integer [after, nullable].

## 2025_08_05_141026_add_remaining_amount.php

[database/migrations/2025_08_05_141026_add_remaining_amount.php](../../../database/migrations/2025_08_05_141026_add_remaining_amount.php)

- **table** `coc_log`: `remaining_amount`: decimal(5,2) [after, nullable].

## 2025_08_08_145416_add_fields_coclog.php

[database/migrations/2025_08_08_145416_add_fields_coclog.php](../../../database/migrations/2025_08_08_145416_add_fields_coclog.php)

- **table** `coc_log`: `remarks`: string [after, nullable]; `logged_by`: string [after, nullable].

## 2025_08_26_083815_create_real_properties_table.php

[database/migrations/2025_08_26_083815_create_real_properties_table.php](../../../database/migrations/2025_08_26_083815_create_real_properties_table.php)

- **create** `saln_real_properties`: `(implicit/multiple columns)`: id; `saln_id`: unsignedBigInteger; `description`: string; `kind`: string; `exact_location`: string; `assessed_value`: decimal(15,2); `current_fair`: decimal(15,2); `acquisition_year`: string; `acquisition_mode`: string; `acquisition_cost`: decimal(15,2); `(implicit/multiple columns)`: timestamps.

## 2025_08_26_090212_create_saln_table.php

[database/migrations/2025_08_26_090212_create_saln_table.php](../../../database/migrations/2025_08_26_090212_create_saln_table.php)

- **create** `salns`: `id`: bigIncrements; `emp_id`: unsignedBigInteger; `year`: year; `filing_type`: string; `(implicit/multiple columns)`: timestamps.

## 2025_08_27_130948_create_declarant_info_table.php

[database/migrations/2025_08_27_130948_create_declarant_info_table.php](../../../database/migrations/2025_08_27_130948_create_declarant_info_table.php)

- **create** `saln_declarant_info`: `(implicit/multiple columns)`: id; `saln_id`: unsignedBigInteger; `dec_lname`: string [nullable]; `dec_mname`: string [nullable]; `dec_fname`: string [nullable]; `dec_address`: string [nullable]; `dec_position`: string [nullable]; `dec_office`: string [nullable]; `dec_office_address`: string [nullable]; `spouse_lname`: string [nullable]; `spouse_mname`: string [nullable]; `spouse_fname`: string [nullable]; `spouse_position`: string [nullable]; `spouse_office`: string [nullable]; `spouse_office_address`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2025_08_27_172557_create_children_info_table.php

[database/migrations/2025_08_27_172557_create_children_info_table.php](../../../database/migrations/2025_08_27_172557_create_children_info_table.php)

- **create** `saln_children_info`: `(implicit/multiple columns)`: id; `saln_id`: unsignedBigInteger; `name`: string [nullable]; `date_of_birth`: date [nullable]; `age`: integer [nullable]; `(implicit/multiple columns)`: timestamps.

## 2025_09_01_170223_create_personal_prop_table.php

[database/migrations/2025_09_01_170223_create_personal_prop_table.php](../../../database/migrations/2025_09_01_170223_create_personal_prop_table.php)

- **create** `saln_personal_properties`: `(implicit/multiple columns)`: id; `saln_id`: unsignedBigInteger; `description`: string; `year_acquired`: string; `acquisition_cost`: decimal(15,2); `(implicit/multiple columns)`: timestamps.

## 2025_09_02_174135_create_liabilities_table.php

[database/migrations/2025_09_02_174135_create_liabilities_table.php](../../../database/migrations/2025_09_02_174135_create_liabilities_table.php)

- **create** `saln_liabilities`: `(implicit/multiple columns)`: id; `saln_id`: unsignedBigInteger; `nature`: string; `creditor`: string; `balance`: decimal(15,2); `(implicit/multiple columns)`: timestamps.

## 2025_09_02_184110_create_business_interests_table.php

[database/migrations/2025_09_02_184110_create_business_interests_table.php](../../../database/migrations/2025_09_02_184110_create_business_interests_table.php)

- **create** `saln_business_interests`: `(implicit/multiple columns)`: id; `saln_id`: unsignedBigInteger; `business_name`: string [nullable]; `business_address`: string [nullable]; `business_nature`: string [nullable]; `acquisition_date`: date [nullable]; `(implicit/multiple columns)`: timestamps.

## 2025_09_02_190051_create_govt_relatives_table.php

[database/migrations/2025_09_02_190051_create_govt_relatives_table.php](../../../database/migrations/2025_09_02_190051_create_govt_relatives_table.php)

- **create** `saln_govt_relatives`: `(implicit/multiple columns)`: id; `saln_id`: unsignedBigInteger; `name`: string [nullable]; `relationship`: string [nullable]; `position`: string [nullable]; `office`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2025_09_04_182013_create_concerns_table.php

[database/migrations/2025_09_04_182013_create_concerns_table.php](../../../database/migrations/2025_09_04_182013_create_concerns_table.php)

- **create** `concerns`: `(implicit/multiple columns)`: id; `employee_id`: integer; `subject`: string; `description`: string; `response`: string [nullable]; `status`: string [default]; `hr_id`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2025_09_04_182337_create_concern_uploads_table.php

[database/migrations/2025_09_04_182337_create_concern_uploads_table.php](../../../database/migrations/2025_09_04_182337_create_concern_uploads_table.php)

- **create** `concern_uploads`: `(implicit/multiple columns)`: id; `concern_id`: integer; `file_path`: string; `(implicit/multiple columns)`: timestamps.

## 2025_09_17_135736_change_credit_type.php

[database/migrations/2025_09_17_135736_change_credit_type.php](../../../database/migrations/2025_09_17_135736_change_credit_type.php)

- **table** `coc_log`: `credit_amount`: integer [change]; `total_credits`: integer [change]; `remaining_amount`: integer [change].

## 2025_10_27_143254_add_current_balance.php

[database/migrations/2025_10_27_143254_add_current_balance.php](../../../database/migrations/2025_10_27_143254_add_current_balance.php)

- **table** `c_t_o_s`: `current_balance`: integer [after, nullable].

## 2026_01_14_115139_create_division_lnd_plans_table.php

[database/migrations/2026_01_14_115139_create_division_lnd_plans_table.php](../../../database/migrations/2026_01_14_115139_create_division_lnd_plans_table.php)

- **create** `division_lnd_plans`: `(implicit/multiple columns)`: id; `division_id`: integer; `year`: string; `total_budget`: decimal(15,2) [default]; `staff_count`: integer [default]; `created_by`: integer; `(implicit/multiple columns)`: timestamps.

## 2026_01_14_134233_create_ipcrs_table.php

[database/migrations/2026_01_14_134233_create_ipcrs_table.php](../../../database/migrations/2026_01_14_134233_create_ipcrs_table.php)

- **create** `ipcrs`: `(implicit/multiple columns)`: id; `user_id`: unsignedBigInteger; `period_start`: date; `period_end`: date; `overall_rating`: decimal(3,2) [nullable]; `(implicit/multiple columns)`: timestamps.

## 2026_01_14_154827_create_ipcr_items_table.php

[database/migrations/2026_01_14_154827_create_ipcr_items_table.php](../../../database/migrations/2026_01_14_154827_create_ipcr_items_table.php)

- **create** `ipcr_items`: `(implicit/multiple columns)`: id; `ipcr_id`: unsignedBigInteger; `task_type`: enum; `assigned_tasks`: string; `success_indicator`: string; `actual_accomplishment`: string [nullable]; `quality`: decimal(3,2); `quantity`: decimal(3,2); `timeliness`: decimal(3,2); `average_rating`: decimal(3,2) [nullable]; `(implicit/multiple columns)`: timestamps.

## 2026_01_22_101352_create_outputs_table.php

[database/migrations/2026_01_22_101352_create_outputs_table.php](../../../database/migrations/2026_01_22_101352_create_outputs_table.php)

- **create** `outputs`: `(implicit/multiple columns)`: id; `ipcr_id`: unsignedBigInteger; `category_id`: unsignedBigInteger; `task`: text; `(implicit/multiple columns)`: timestamps.

## 2026_01_22_112326_create_success_indicators_table.php

[database/migrations/2026_01_22_112326_create_success_indicators_table.php](../../../database/migrations/2026_01_22_112326_create_success_indicators_table.php)

- **create** `success_indicators`: `(implicit/multiple columns)`: id; `ipcr_id`: unsignedBigInteger; `output_id`: string; `indicator_description`: text; `(implicit/multiple columns)`: timestamps.

## 2026_01_26_173920_create_actual_accomplishments_table.php

[database/migrations/2026_01_26_173920_create_actual_accomplishments_table.php](../../../database/migrations/2026_01_26_173920_create_actual_accomplishments_table.php)

- **create** `actual_accomplishments`: `(implicit/multiple columns)`: id; `ipcr_id`: unsignedBigInteger; `si_id`: string; `accomplishment`: text; `(implicit/multiple columns)`: timestamps.

## 2026_01_28_135156_add_concerns_columns.php

[database/migrations/2026_01_28_135156_add_concerns_columns.php](../../../database/migrations/2026_01_28_135156_add_concerns_columns.php)

- **table** `concerns`: `date_resolved`: string [nullable, after]; `file_upload`: string [nullable, after]; `hr_file`: string [nullable, after].

## 2026_01_28_170035_add_rating_field.php

[database/migrations/2026_01_28_170035_add_rating_field.php](../../../database/migrations/2026_01_28_170035_add_rating_field.php)

- **table** `actual_accomplishments`: `quality`: integer [after, nullable]; `efficiency`: integer [after, nullable]; `timeliness`: integer [after, nullable].

## 2026_02_23_140832_create_coe_table.php

[database/migrations/2026_02_23_140832_create_coe_table.php](../../../database/migrations/2026_02_23_140832_create_coe_table.php)

- **create** `coe`: `(implicit/multiple columns)`: id; `employee_id`: string [nullable]; `request_id`: string [nullable]; `category`: string [nullable]; `prefix`: string [nullable]; `position_id`: string [nullable]; `salary_grade`: string [nullable]; `division_id`: string [nullable]; `date_hired`: string [nullable]; `termination_date`: string [nullable]; `status`: string [nullable]; `other_status`: string [nullable]; `forwarded_to`: string [nullable]; `reason`: string [nullable]; `hr_response`: string [nullable]; `e_signature`: string [nullable]; `basic_pay`: string [nullable]; `pera`: string [nullable]; `midyear`: string [nullable]; `yearend`: string [nullable]; `cna`: string [nullable]; `sri`: string [nullable]; `clothing`: string [nullable]; `medical`: string [nullable]; `pei`: string [nullable]; `cash_gift`: string [nullable]; `annual_total`: string [nullable]; `benefits_total`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2026_02_23_141527_add_grand_total_column.php

[database/migrations/2026_02_23_141527_add_grand_total_column.php](../../../database/migrations/2026_02_23_141527_add_grand_total_column.php)

- **table** `coe`: `grand_total`: string [nullable, after].

## 2026_02_23_142753_add_acknowledged_by_column.php

[database/migrations/2026_02_23_142753_add_acknowledged_by_column.php](../../../database/migrations/2026_02_23_142753_add_acknowledged_by_column.php)

- **table** `coe`: `acknowledged_by`: string [nullable, after].

## 2026_02_24_150619_add_request_file_column.php

[database/migrations/2026_02_24_150619_add_request_file_column.php](../../../database/migrations/2026_02_24_150619_add_request_file_column.php)

- **table** `coe`: `requested_file`: string [nullable, after].

## 2026_03_02_101559_create_oics_table.php

[database/migrations/2026_03_02_101559_create_oics_table.php](../../../database/migrations/2026_03_02_101559_create_oics_table.php)

- **create** `oics`: `(implicit/multiple columns)`: id; `dc_id`: integer; `period_start`: date; `period_end`: date; `(implicit/multiple columns)`: timestamps.

## 2026_03_02_131309_cert_employed.php

[database/migrations/2026_03_02_131309_cert_employed.php](../../../database/migrations/2026_03_02_131309_cert_employed.php)

- **create** `coe_employed`: `(implicit/multiple columns)`: id; `employee_id`: string; `prefix`: string; `position_id`: string; `salary_grade`: string; `division_id`: string; `date_hired`: string; `status`: string; `forwarded_to`: string; `acknowledged_by`: string; `reason`: string; `hr_response`: string; `e_signature`: string; `requested_file`: string; `basic_pay`: string; `pera`: string; `mid_year`: string; `year_end`: string; `cna`: string; `sri`: string; `clothing_allowance`: string; `medical_allowance`: string; `pei`: string; `cash_gift`: string; `annual_total`: string; `benefits_total`: string; `grand_total`: string; `(implicit/multiple columns)`: timestamps.

## 2026_03_02_134419_add_request_id_column.php

[database/migrations/2026_03_02_134419_add_request_id_column.php](../../../database/migrations/2026_03_02_134419_add_request_id_column.php)

- **table** `coe_employed`: `request_id`: string [nullable, after].

## 2026_03_02_170406_cert_manual.php

[database/migrations/2026_03_02_170406_cert_manual.php](../../../database/migrations/2026_03_02_170406_cert_manual.php)

- **create** `coe_manual`: `(implicit/multiple columns)`: id; `request_id`: string [nullable]; `prefix`: string [nullable]; `fullname`: string [nullable]; `position_id`: string [nullable]; `division_id`: string [nullable]; `date_hired`: string [nullable]; `termination_date`: string [nullable]; `status`: string [nullable]; `forwarded_to`: string [nullable]; `acknowledged_by`: string [nullable]; `hr_response`: string [nullable]; `e_signature`: string [nullable]; `requested_file`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2026_03_03_110528_add_hr_position_column.php

[database/migrations/2026_03_03_110528_add_hr_position_column.php](../../../database/migrations/2026_03_03_110528_add_hr_position_column.php)

- **table** `coe_employed`: `hr_position`: string [nullable, after].

## 2026_03_03_133121_add_employee_id_manual_column.php

[database/migrations/2026_03_03_133121_add_employee_id_manual_column.php](../../../database/migrations/2026_03_03_133121_add_employee_id_manual_column.php)

- **table** `coe_manual`: `employee_id`: string [after, nullable].

## 2026_03_03_134449_drop_full_name_column.php

[database/migrations/2026_03_03_134449_drop_full_name_column.php](../../../database/migrations/2026_03_03_134449_drop_full_name_column.php)

- **table** `coe_manual`: `fullname`: dropColumn.

Includes raw SQL or rename/drop columns; read the migration before mapping final fields.

## 2026_03_09_195856_wfh_location.php

[database/migrations/2026_03_09_195856_wfh_location.php](../../../database/migrations/2026_03_09_195856_wfh_location.php)

- **table** `dtr_time_log`: `wfh_location`: text [nullable, after].

## 2026_03_16_102842_create_ssi_links_table.php

[database/migrations/2026_03_16_102842_create_ssi_links_table.php](../../../database/migrations/2026_03_16_102842_create_ssi_links_table.php)

- **create** `ssi_links`: `(implicit/multiple columns)`: id; `name`: string; `description`: text [nullable]; `url`: string; `added_by`: unsignedBigInteger; `status`: string [nullable]; `(implicit/multiple columns)`: timestamps.

## 2026_03_16_111200_add_status_column.php

[database/migrations/2026_03_16_111200_add_status_column.php](../../../database/migrations/2026_03_16_111200_add_status_column.php)

- **table** `ssis`: `status`: string [after].

## 2026_04_14_120000_create_csw_payroll_staff_table.php

[database/migrations/2026_04_14_120000_create_csw_payroll_staff_table.php](../../../database/migrations/2026_04_14_120000_create_csw_payroll_staff_table.php)

- **create** `csw_payroll_staff`: `(implicit/multiple columns)`: id; `payroll_id`: integer; `user_id`: integer; `salary_contractual`: double(12,2) [default, nullable]; `periodic_salary`: double(12,2) [default, nullable]; `late`: double(12,2) [default, nullable]; `undertime`: double(12,2) [default, nullable]; `Absence`: double(12,2) [default, nullable]; `withholding_tax_3`: double(12,2) [default, nullable]; `withholding_tax_5`: double(12,2) [default, nullable]; `total_tax`: double(12,2) [default, nullable]; `total_deduction`: double(12,2) [default, nullable]; `net_amount`: double(12,2) [default, nullable]; `(implicit/multiple columns)`: timestamps.

## 2026_04_14_130000_add_mode_of_payment_and_fund_cluster_to_csw_payroll_staff_table.php

[database/migrations/2026_04_14_130000_add_mode_of_payment_and_fund_cluster_to_csw_payroll_staff_table.php](../../../database/migrations/2026_04_14_130000_add_mode_of_payment_and_fund_cluster_to_csw_payroll_staff_table.php)

- **table** `csw_payroll_staff`: `mode_of_payment`: string [nullable, after]; `fund_cluster`: string [nullable, after].

## 2026_04_16_000000_drop_csw_payroll_staff_table.php

[database/migrations/2026_04_16_000000_drop_csw_payroll_staff_table.php](../../../database/migrations/2026_04_16_000000_drop_csw_payroll_staff_table.php)

- **dropIfExists** `csw_payroll_staff`.

## 2026_04_16_010000_add_csw_columns_to_payroll_staff_table.php

[database/migrations/2026_04_16_010000_add_csw_columns_to_payroll_staff_table.php](../../../database/migrations/2026_04_16_010000_add_csw_columns_to_payroll_staff_table.php)

- **table** `payroll_staff`: `csw_mode_of_payment`: string [nullable, after]; `csw_fund_cluster`: string [nullable, after]; `csw_late`: double(12,2) [default, nullable, after]; `csw_undertime`: double(12,2) [default, nullable, after]; `csw_Absence`: double(12,2) [default, nullable, after]; `csw_withholding_tax_3`: double(12,2) [default, nullable, after]; `csw_withholding_tax_5`: double(12,2) [default, nullable, after].

## 2026_05_07_171330_add_saln_fields.php

[database/migrations/2026_05_07_171330_add_saln_fields.php](../../../database/migrations/2026_05_07_171330_add_saln_fields.php)

- **table** `salns`: `compliance_type`: integer [after]; `compliance_date`: date [after]; `year`: dropColumn.

Includes raw SQL or rename/drop columns; read the migration before mapping final fields.

## 2026_05_12_000000_create_loans_table.php

[database/migrations/2026_05_12_000000_create_loans_table.php](../../../database/migrations/2026_05_12_000000_create_loans_table.php)

- **create** `loans`: `(implicit/multiple columns)`: id; `loan_provider`: string; `loan_name`: string.

## 2026_05_12_000001_create_payroll_loan_table.php

[database/migrations/2026_05_12_000001_create_payroll_loan_table.php](../../../database/migrations/2026_05_12_000001_create_payroll_loan_table.php)

- **create** `payroll_loan`: `(implicit/multiple columns)`: id; `payroll_id`: string; `user_id`: string; `loan_id`: string; `amount`: double(12,2) [nullable, default].

## 2026_05_14_090000_create_work_from_home_accomplishments_table.php

[database/migrations/2026_05_14_090000_create_work_from_home_accomplishments_table.php](../../../database/migrations/2026_05_14_090000_create_work_from_home_accomplishments_table.php)

- **create** `work_from_home_accomplishments`: `id`: bigIncrements; `user_id`: unsignedBigInteger; `division_id`: unsignedBigInteger; `wfh_date`: date; `accomplishments`: text; `status`: string [default]; `approved_by`: unsignedBigInteger [nullable]; `approved_at`: timestamp [nullable]; `returned_by`: unsignedBigInteger [nullable]; `returned_at`: timestamp [nullable]; `return_remarks`: text [nullable]; `(implicit/multiple columns)`: timestamps; `(implicit/multiple columns)`: unique; `(implicit/multiple columns)`: index; `(implicit/multiple columns)`: index.

## 2026_05_14_090100_create_work_from_home_consolidated_reports_table.php

[database/migrations/2026_05_14_090100_create_work_from_home_consolidated_reports_table.php](../../../database/migrations/2026_05_14_090100_create_work_from_home_consolidated_reports_table.php)

- **create** `work_from_home_consolidated_reports`: `id`: bigIncrements; `division_id`: unsignedBigInteger; `division_chief_id`: unsignedBigInteger; `wfh_date`: date; `submitted_count`: unsignedInteger [default]; `status`: string [default]; `signed_at`: timestamp [nullable]; `approved_by`: unsignedBigInteger [nullable]; `approved_at`: timestamp [nullable]; `(implicit/multiple columns)`: timestamps; `(implicit/multiple columns)`: unique; `(implicit/multiple columns)`: index; `(implicit/multiple columns)`: index.

## 2026_06_04_141449_modify_fields.php

[database/migrations/2026_06_04_141449_modify_fields.php](../../../database/migrations/2026_06_04_141449_modify_fields.php)

- **table** `saln_declarant_info`: `dec_address`: dropColumn.

Includes raw SQL or rename/drop columns; read the migration before mapping final fields.

## 2026_06_08_135216_add_wellness_table.php

[database/migrations/2026_06_08_135216_add_wellness_table.php](../../../database/migrations/2026_06_08_135216_add_wellness_table.php)

- **create** `wellness_tips`: `(implicit/multiple columns)`: id; `start_date`: date [nullable]; `end_date`: date [nullable]; `title`: string [nullable]; `description`: text [nullable]; `(implicit/multiple columns)`: timestamps.

## 2026_07_07_000001_create_system_notifications_table.php

[database/migrations/2026_07_07_000001_create_system_notifications_table.php](../../../database/migrations/2026_07_07_000001_create_system_notifications_table.php)

- **create** `system_notifications`: `(implicit/multiple columns)`: id; `user_id`: unsignedBigInteger [nullable]; `recipient_email`: string [nullable]; `system`: string(50) [default]; `source`: string(100); `title`: string(200) [nullable]; `message`: text; `url`: text [nullable]; `badge_class`: string(50) [default]; `badge_text`: string(50) [default]; `icon`: string(50) [default]; `priority`: integer [default]; `notification_type`: enum [default]; `target_users`: json [nullable]; `is_read`: boolean [default]; `is_dismissed`: boolean [default]; `read_at`: timestamp [nullable]; `dismissed_at`: timestamp [nullable]; `expires_at`: timestamp [nullable]; `is_active`: boolean [default]; `(implicit/multiple columns)`: timestamps; `user_id`: index; `recipient_email`: index; `(implicit/multiple columns)`: index; `system`: index; `expires_at`: index.

## 2026_07_07_000002_add_recipient_email_to_system_notifications_table.php

[database/migrations/2026_07_07_000002_add_recipient_email_to_system_notifications_table.php](../../../database/migrations/2026_07_07_000002_add_recipient_email_to_system_notifications_table.php)

- **table** `system_notifications`: `recipient_email`: string [nullable, after]; `recipient_email`: index.

