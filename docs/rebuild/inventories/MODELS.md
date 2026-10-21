# Legacy model inventory

Source revision: `c8973c067292967581b0ae5a2fe8493f905fed4c`. Generated from the working tree by [generate-inventories.cjs](../tools/generate-inventories.cjs). This is static source evidence, not a running application or live database inspection. Re-run after source changes.

Entries marked implicit show the model class label when no $table override is declared; inferred framework table names are not computed here. Confirm those names against the schema. Fillable attributes describe mass assignment, not the full schema. Relationships are literal declarations; they may use unusual foreign keys or be incorrect.

| Model | Table | Fillable attributes | Declared relationships |
| --- | --- | --- | --- |
| [app/ActualAccomplishments.php](../../../app/ActualAccomplishments.php) | `actual_accomplishments` | (not declared) | hasOne(SuccessIndicators::class, 'id', 'si_id') |
| [app/Announcements.php](../../../app/Announcements.php) | `ssi_announcements` | (not declared) | (none declared) |
| [app/BdayFile.php](../../../app/BdayFile.php) | `bday_file` | (not declared) | (none declared) |
| [app/CTO.php](../../../app/CTO.php) | `c_t_o_s` | (not declared) | hasMany(Status::class, 'request_id', 'id')->where('request_type', 'CTO'); hasMany(\App\CTODates::class, 'cto_id'); hasOne(User::class, 'id', 'emp_id') |
| [app/CTODates.php](../../../app/CTODates.php) | `CTODates (implicit table)` | (not declared) | (none declared) |
| [app/CertEmployed.php](../../../app/CertEmployed.php) | `coe_employed` | employee_id, request_id, prefix, position_id, salary_grade, division_id, date_hired, status, forwarded_to, acknowledged_by, hr_position, reason, hr_response, e_signature, requested_file, basic_pay, pera, mid_year, year_end, cna, sri, clothing_allowance, medical_allowance, pei, cash_gift, annual_total, benefits_total, grand_total | belongsTo(User::class, 'employee_id', 'id'); belongsTo(Position::class, 'position_id', 'id'); belongsTo(Division::class, 'division_id', 'id'); belongsTo(Position::class, 'salary_grade', 'id'); belongsTo(SalaryGrade::class, 'salary_grade', 'id'); belongsTo(Position::class, 'hr_position', 'id') |
| [app/CertManual.php](../../../app/CertManual.php) | `coe_manual` | employee_id, request_id, prefix, position_id, division_id, date_hired, termination_date, status, forwarded_to, acknowledged_by, hr_response, e_signature, requested_file | belongsTo(User::class, 'employee_id', 'id'); belongsTo(Position::class, 'position_id', 'id'); belongsTo(Division::class, 'division_id', 'id') |
| [app/CocLog.php](../../../app/CocLog.php) | `coc_log` | user_id, credit_amount, total_credits, status, remaining_amount, remarks, scope_date, logged_by | (none declared) |
| [app/Concern.php](../../../app/Concern.php) | `concerns` | employee_id, subject, description, response, date_resolved, status, hr_id, file_upload, hr_file | belongsTo(User::class, 'employee_id', 'id'); belongsTo(User::class, 'hr_id', 'id') |
| [app/ConcernUpload.php](../../../app/ConcernUpload.php) | `concern_uploads` | concern_id, file_path | (none declared) |
| [app/CoreFunctions.php](../../../app/CoreFunctions.php) | `core_functions` | (not declared) | hasOne(StrategicPriorities::class, 'id', 'strategic_priorities_id') |
| [app/DTR.php](../../../app/DTR.php) | `dtr` | user_id, time_in, lunch_out, lunch_in, time_out, ot_in, ot_out, hours_rendered, ot_hours_rendered, created_at_date, work_sched, late, notes, undertime | belongsTo(LeaveLog::class); hasOne(User::class, 'id', 'user_id') |
| [app/DTRSummary.php](../../../app/DTRSummary.php) | `dtr_summary` | (not declared) | (none declared) |
| [app/DTRTimeLog.php](../../../app/DTRTimeLog.php) | `dtr_time_log` | anviz_log_id, user_atms_id, time_log, device, record_type, wfh_location, created_at, updated_at | belongsTo(DTR::class); hasOne(User::class, 'atms_id', 'user_atms_id') |
| [app/DataSheet.php](../../../app/DataSheet.php) | `DataSheet (implicit table)` | (not declared) | (none declared) |
| [app/Division.php](../../../app/Division.php) | `Division (implicit table)` | (not declared) | hasOne(CertManual::class, 'division_id', 'id'); hasOne(User::class, 'division_id', 'id'); hasOne(CertEmployed::class, 'division_id', 'id') |
| [app/DivisionLndPlan.php](../../../app/DivisionLndPlan.php) | `division_lnd_plans` | division_id, year, total_budget, staff_count, created_by | belongsTo(Division::class, 'division_id'); belongsTo(User::class, 'created_by') |
| [app/DtrActivity.php](../../../app/DtrActivity.php) | `DtrActivity (implicit table)` | (not declared) | (none declared) |
| [app/DtrRemarks.php](../../../app/DtrRemarks.php) | `dtr_remarks` | (not declared) | (none declared) |
| [app/Events.php](../../../app/Events.php) | `ssi_events` | (not declared) | hasOne(Division::class, 'id', 'division_id') |
| [app/Holiday.php](../../../app/Holiday.php) | `holidays` | (not declared) | (none declared) |
| [app/IDP.php](../../../app/IDP.php) | `users_idp` | idp_year, user_id, curr_position, sg, yrs_position, yrs_agency, year1, year2, year3, purpose, indicate_purpose, submit_date, dc_approved, dc_approve_date, hrdc_approved, hrdc_approve_date, training | hasOne(User::class, 'id', 'user_id') |
| [app/Ipcr.php](../../../app/Ipcr.php) | `ipcrs` | user_id, period_start, period_end | hasOne(User::class, 'id', 'user_id'); hasOne(Position::class, 'id', 'user_id')->select('id', 'position') |
| [app/IpcrItems.php](../../../app/IpcrItems.php) | `IpcrItems (implicit table)` | (not declared) | (none declared) |
| [app/Leave.php](../../../app/Leave.php) | `leaves` | (not declared) | hasMany(Status::class, 'request_id', 'id')->where('request_type', 'LEAVE'); hasOne(User::class, 'id', 'user_id'); hasMany(LeaveDates::class, 'leave_id', 'id'); hasOne(LeaveType::class, 'id', 'type_of_leave'); hasOne(Status::class, 'request_id', 'id')->where('request_type', 'LEAVE')->orderBy('id', 'DESC') |
| [app/LeaveCreditMinuteBasis.php](../../../app/LeaveCreditMinuteBasis.php) | `LeaveCreditMinuteBasis (implicit table)` | (not declared) | (none declared) |
| [app/LeaveDates.php](../../../app/LeaveDates.php) | `leave_dates` | leave_id, inclusive_date | belongsTo(LeaveLog::class) |
| [app/LeaveLog.php](../../../app/LeaveLog.php) | `leave_log` | user_id, type_of_leave, activity, credit_amount, total_credits, status | hasOne(LeaveType::class, 'id', 'type_of_leave') |
| [app/LeaveType.php](../../../app/LeaveType.php) | `leave_types` | (not declared) | (none declared) |
| [app/Link.php](../../../app/Link.php) | `ssi_links` | name, description, url, added_by, status | belongsTo(User::class, 'added_by', 'id') |
| [app/Loans.php](../../../app/Loans.php) | `loans` | loan_provider, loan_name | (none declared) |
| [app/OIC.php](../../../app/OIC.php) | `oics` | (not declared) | (none declared) |
| [app/OfficialBusiness.php](../../../app/OfficialBusiness.php) | `OfficialBusiness (implicit table)` | (not declared) | hasMany(Status::class, 'request_id', 'id'); hasOne(User::class, 'id', 'emp_id'); hasMany(\App\Status::class, 'request_id'); hasOne(Status::class)->orderBy('id', 'DESC') |
| [app/Outputs.php](../../../app/Outputs.php) | `outputs` | (not declared) | (none declared) |
| [app/Overtime.php](../../../app/Overtime.php) | `Overtime (implicit table)` | (not declared) | (none declared) |
| [app/OvertimeDates.php](../../../app/OvertimeDates.php) | `OvertimeDates (implicit table)` | (not declared) | (none declared) |
| [app/Payroll.php](../../../app/Payroll.php) | `payroll` | (not declared) | (none declared) |
| [app/PayrollLoans.php](../../../app/PayrollLoans.php) | `payroll_loan` | payroll_id, user_id, loan_id, amount | (none declared) |
| [app/PayrollStaff.php](../../../app/PayrollStaff.php) | `payroll_staff` | payroll_id, user_id, salary_regular, salary_contractual, pera, rata, tax, rlip, philhealth, hdmf, hdmf_mp2, gsis_opt_policy, assert, conso_loan, regular_pol_loan, opt_pol_loan, gfal, emergency_loan, gsis_educ_loan, lowcos_house_loan, cpl, mpl, mult_purp_loan, calamity_loan, live_program, disallowance, gross, total_deduction, net, other_compensation_name, other_compensation_amount, other_deduction_name, other_deduction_amount, periodic_salary, phic, bonuses, late, undertime, Absence, csw_mode_of_payment, csw_fund_cluster, csw_late, csw_undertime, csw_Absence, csw_withholding_tax_3, csw_withholding_tax_5 | belongsTo(User::class, 'user_id', 'id') |
| [app/Position.php](../../../app/Position.php) | `positions` | (not declared) | hasOne(User::class, 'position_id', 'id'); belongsTo(Division::class, 'division_id', 'id'); hasMany(UserLDMatrix::class, 'position_id', 'id'); hasOne(CertManual::class, 'position_id', 'id'); hasOne(SalaryGrade::class, 'id', 'salary_grade'); belongsTo(SalaryGrade::class, 'salary_grade', 'id'); belongsTo(SalaryGrade::class, 'salary_grade', 'id'); hasOne(User::class, 'position_id', 'id'); hasOne(User::class, 'position_id', 'id'); hasOne(CertEmployed::class, 'hr_position', 'id') |
| [app/PositionEvaluators.php](../../../app/PositionEvaluators.php) | `position_evaluators` | (not declared) | (none declared) |
| [app/PositionHistory.php](../../../app/PositionHistory.php) | `positions_history` | (not declared) | hasOne(Position::class, 'id', 'position_id'); hasOne(User::class, 'id', 'user_id'); hasOne(User::class, 'id', 'hr_user_id') |
| [app/RCategory.php](../../../app/RCategory.php) | `r_categories` | (not declared) | (none declared) |
| [app/RChoices.php](../../../app/RChoices.php) | `r_choices` | (not declared) | (none declared) |
| [app/REvalDocs.php](../../../app/REvalDocs.php) | `r_eval_docs` | (not declared) | (none declared) |
| [app/RExamAnswer.php](../../../app/RExamAnswer.php) | `r_exam_answers` | (not declared) | (none declared) |
| [app/RExamTimer.php](../../../app/RExamTimer.php) | `r_exam_timer` | (not declared) | (none declared) |
| [app/RPositionApplicant.php](../../../app/RPositionApplicant.php) | `r_position_applicant` | (not declared) | (none declared) |
| [app/RPositionCategory.php](../../../app/RPositionCategory.php) | `r_position_category` | (not declared) | (none declared) |
| [app/RQuestion.php](../../../app/RQuestion.php) | `r_questions` | (not declared) | (none declared) |
| [app/RecognitionAwardVote.php](../../../app/RecognitionAwardVote.php) | `RecognitionAwardVote (implicit table)` | (not declared) | belongsTo(User::class, 'nominees_id') |
| [app/RecognitionReward.php](../../../app/RecognitionReward.php) | `RecognitionReward (implicit table)` | start, end, type, nominees, winners, recognition_date, recognition_name | (none declared) |
| [app/SALN.php](../../../app/SALN.php) | `salns` | emp_id, filing_type, compliance_type, compliance_date | (none declared) |
| [app/SalaryGrade.php](../../../app/SalaryGrade.php) | `salary_grade` | (not declared) | hasOne(Position::class, 'salary_grade', 'id'); hasOne(Position::class, 'salary_grade', 'id'); hasOne(CertEmployed::class, 'salary_grade', 'id') |
| [app/SalnBusinessInterests.php](../../../app/SalnBusinessInterests.php) | `saln_business_interests` | saln_id, business_name, business_address, business_nature, acquisition_date | (none declared) |
| [app/SalnChildrenInfo.php](../../../app/SalnChildrenInfo.php) | `saln_children_info` | saln_id, name, date_of_birth, age | (none declared) |
| [app/SalnDeclarantInfo.php](../../../app/SalnDeclarantInfo.php) | `saln_declarant_info` | saln_id, dec_lname, dec_mname, dec_fname, dec_position, dec_office, dec_office_address, spouse_lname, spouse_mname, spouse_fname, spouse_position, spouse_office, spouse_office_address | (none declared) |
| [app/SalnGovernmentRelatives.php](../../../app/SalnGovernmentRelatives.php) | `saln_govt_relatives` | saln_id, name, relationship, position, office | (none declared) |
| [app/SalnLiabilities.php](../../../app/SalnLiabilities.php) | `saln_liabilities` | saln_id, nature, creditor, balance | (none declared) |
| [app/SalnPersonalAssets.php](../../../app/SalnPersonalAssets.php) | `saln_personal_properties` | saln_id, description, year_acquired, acquisition_cost | (none declared) |
| [app/SalnRealAssets.php](../../../app/SalnRealAssets.php) | `saln_real_properties` | saln_id, description, kind, exact_location, assessed_value, current_fair, acquisition_year, acquisition_mode, acquisition_cost | (none declared) |
| [app/SalnUnmarriedChildren.php](../../../app/SalnUnmarriedChildren.php) | `SalnUnmarriedChildren (implicit table)` | (not declared) | (none declared) |
| [app/Ssi.php](../../../app/Ssi.php) | `ssis` | url, title, description, img, status | (none declared) |
| [app/Status.php](../../../app/Status.php) | `statuses` | request_id, request_type, status, person_in_charge, remarks, auto_approve | (none declared) |
| [app/StrategicPriorities.php](../../../app/StrategicPriorities.php) | `strategic_priorities` | (not declared) | (none declared) |
| [app/SuccessIndicators.php](../../../app/SuccessIndicators.php) | `success_indicators` | (not declared) | hasOne(Outputs::class, 'id', 'output_id') |
| [app/SystemNotification.php](../../../app/SystemNotification.php) | `system_notifications` | user_id, recipient_email, system, source, title, message, url, badge_class, badge_text, icon, priority, notification_type, target_users, is_read, is_dismissed, read_at, dismissed_at, expires_at, is_active | belongsTo(User::class) |
| [app/Table1.php](../../../app/Table1.php) | `table_1` | (not declared) | (none declared) |
| [app/Table2.php](../../../app/Table2.php) | `table_2` | (not declared) | (none declared) |
| [app/Table3.php](../../../app/Table3.php) | `table_3` | (not declared) | (none declared) |
| [app/Table4.php](../../../app/Table4.php) | `table_4` | (not declared) | (none declared) |
| [app/Upload.php](../../../app/Upload.php) | `uploads` | (not declared) | (none declared) |
| [app/User.php](../../../app/User.php) | `users` | firstname, lastname, middlename, date_hired, nickname, division_id, position_id, employee_type, home_address, emergency_address, signature, img, email, id_no, bp_no, pag_ibig, step_increment, tin_no, philhealth, blood_type, contact_no, contact_person, contact_person_no, birthday, user_type, sex, password, atms_id, solo_parent, termination_date, workweek | hasOne(Link::class, 'added_by', 'id'); belongsTo(Position::class, 'position_id', 'id'); hasOne(CertManual::class, 'employee_id', 'id'); hasOne(CertEmployed::class, 'employee_id', 'id'); belongsTo(Position::class, 'position_id', 'id'); belongsTo(Division::class, 'division_id', 'id'); belongsTo(Division::class, 'division_id', 'id'); hasOne(Concern::class, 'employee_id', 'id'); hasOne(Concern::class, 'hr_id', 'id'); hasOne(Division::class, 'id', 'division_id'); hasMany(IPCR::class, 'user_id', 'id'); hasMany(DTRTimeLog::class, 'user_atms_id', 'atms_id'); hasOne(Position::class, 'id', 'position_id'); hasMany(UserEduc::class, 'user_id', 'id'); hasMany(DTR::class, 'user_id', 'id'); hasMany(LeaveLog::class, 'user_id', 'id'); hasMany(Leave::class, 'user_id', 'id'); hasMany(OfficialBusiness::class, 'emp_id', 'id'); hasMany(Overtime::class, 'emp_id', 'id'); hasMany(UserCharRef::class, 'user_id', 'id'); hasMany(UserEduc::class, 'user_id', 'id'); hasMany(UserEligibility::class, 'user_id', 'id'); hasMany(UserFam::class, 'user_id', 'id'); hasMany(UserGovDec::class, 'user_id', 'id'); hasMany(UserOtherInfo::class, 'user_id', 'id'); hasMany(UserGovID::class, 'user_id', 'id'); hasMany(UserLD::class, 'user_id', 'id'); hasOne(UserPersonal::class, 'user_id', 'id')->latest(); hasMany(UserVolWork::class, 'user_id', 'id'); hasOne(UserWorkex::class, 'user_id', 'id')->latest(); hasMany(UserLDMatrix::class, 'position_id', 'position_id'); hasMany(IDP::class, 'user_id', 'id')->whereNotNull('submit_date'); hasMany(IDP::class, 'user_id', 'id')->whereNotNull('submit_date') |
| [app/UserCharRef.php](../../../app/UserCharRef.php) | `users_char_ref` | (not declared) | (none declared) |
| [app/UserEduc.php](../../../app/UserEduc.php) | `users_educ` | (not declared) | (none declared) |
| [app/UserEligibility.php](../../../app/UserEligibility.php) | `users_eligibility` | (not declared) | (none declared) |
| [app/UserFam.php](../../../app/UserFam.php) | `users_fam` | (not declared) | (none declared) |
| [app/UserGovDec.php](../../../app/UserGovDec.php) | `users_gov_dec` | (not declared) | (none declared) |
| [app/UserGovID.php](../../../app/UserGovID.php) | `users_gov_id` | (not declared) | (none declared) |
| [app/UserLD.php](../../../app/UserLD.php) | `users_ld` | (not declared) | hasOne(UserLDMatrix::class, 'id', 'matrix_id') |
| [app/UserLDMatrix.php](../../../app/UserLDMatrix.php) | `users_ld_matrix` | category, training_value, position_id | hasMany(UserLDMatrixBudget::class, 'matrix_id', 'id') |
| [app/UserLDMatrixBudget.php](../../../app/UserLDMatrixBudget.php) | `users_ld_matrix_budget` | matrix_id, year, budget, isFree | (none declared) |
| [app/UserOtherInfo.php](../../../app/UserOtherInfo.php) | `users_other_info` | (not declared) | (none declared) |
| [app/UserPersonal.php](../../../app/UserPersonal.php) | `users_personal` | user_id, firstname, lastname, middlename, extension, birthday, place_of_birth, citizenship, citizenship_by, citizenship_country, sex, civil_status, civil_status_details, height, weight, blood_type, gsis, pagibig, philhealth, sss, tin, employee_no, telephone_no, mobile_no, res_house, res_street, res_subdivision, res_baranggay, res_city, res_province, res_zip_code, perm_house, perm_street, perm_subdivision, perm_baranggay, perm_city, perm_province, perm_zip_code, home_address, contact_person, contact_person_no, emergency_address | (none declared) |
| [app/UserVolWork.php](../../../app/UserVolWork.php) | `users_vol_work` | (not declared) | (none declared) |
| [app/UserWorkex.php](../../../app/UserWorkex.php) | `users_workex` | (not declared) | (none declared) |
| [app/WellnessTip.php](../../../app/WellnessTip.php) | `wellness_tips` | start_date, end_date, title, description | (none declared) |
| [app/WorkFromHomeAccomplishment.php](../../../app/WorkFromHomeAccomplishment.php) | `work_from_home_accomplishments` | user_id, division_id, wfh_date, accomplishments, status, approved_by, approved_at, returned_by, returned_at, return_remarks | belongsTo(User::class, 'user_id', 'id'); belongsTo(User::class, 'approved_by', 'id'); belongsTo(User::class, 'returned_by', 'id') |
| [app/WorkFromHomeConsolidatedReport.php](../../../app/WorkFromHomeConsolidatedReport.php) | `work_from_home_consolidated_reports` | division_id, division_chief_id, wfh_date, submitted_count, status, signed_at, approved_by, approved_at | belongsTo(Division::class, 'division_id', 'id'); belongsTo(User::class, 'division_chief_id', 'id'); belongsTo(User::class, 'approved_by', 'id') |
