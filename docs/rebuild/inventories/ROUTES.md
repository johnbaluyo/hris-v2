# Legacy route inventory

Source revision: `c8973c067292967581b0ae5a2fe8493f905fed4c`. Generated from the working tree by [generate-inventories.cjs](../tools/generate-inventories.cjs). This is static source evidence, not a running application or live database inspection. Re-run after source changes.

Explicit routes: **460**. GET entries implicitly also support HEAD in Laravel. Framework-generated auth routes are excluded from this count. Constructor middleware below is extracted text; method-level authorization, trait methods and effective middleware exceptions require source review. The web group supplies sessions and CSRF, not authentication.

[routes/web.php](../../../routes/web.php):29 registers framework auth routes; inspect actual route list for expansions and overrides.

| Method | Path | Handler | Route middleware | Constructor middleware | Source |
| --- | --- | --- | --- | --- | --- |
| GET | `/dtr` | `Auth\LoginController@index` | web | guest->except('logout') | [routes/web.php](../../../routes/web.php):18 |
| GET | `/wfh` | `Auth\LoginController@wfh` | web | guest->except('logout') | [routes/web.php](../../../routes/web.php):19 |
| POST | `/index/check_id` | `Auth\LoginController@check_id` | web | guest->except('logout') | [routes/web.php](../../../routes/web.php):20 |
| POST | `/index/check_last` | `Auth\LoginController@check_last` **MISSING explicit method** | web | guest->except('logout') | [routes/web.php](../../../routes/web.php):21 |
| POST | `/index/cancel` | `Auth\LoginController@cancel` | web | guest->except('logout') | [routes/web.php](../../../routes/web.php):22 |
| POST | `/ssi/login` | `Auth\LoginController@customLogin` | web | guest->except('logout') | [routes/web.php](../../../routes/web.php):24 |
| GET | `/` | `HomeController@index` | web | auth | [routes/web.php](../../../routes/web.php):26 |
| GET | `/logout` | `HomeController@index` | web | auth | [routes/web.php](../../../routes/web.php):27 |
| GET | `/register` | `HomeController@index` | web | auth | [routes/web.php](../../../routes/web.php):30 |
| GET | `/home` | `HomeController@index` | web | auth | [routes/web.php](../../../routes/web.php):32 |
| GET | `/employee/ob` | `OBController@index` | web | auth | [routes/web.php](../../../routes/web.php):33 |
| GET | `/employee/ob/allstaff` | `OBController@allstaff` | web | auth | [routes/web.php](../../../routes/web.php):34 |
| GET | `/employee/ob/allstaff-oic` | `OBController@allstaff_oic` | web | auth | [routes/web.php](../../../routes/web.php):35 |
| POST | `/employee/ob/create` | `OBController@create_ob` | web | auth | [routes/web.php](../../../routes/web.php):36 |
| POST | `/employee/ob/edit` | `OBController@edit_ob` | web | auth | [routes/web.php](../../../routes/web.php):37 |
| POST | `/employee/ob/upload` | `OBController@upload_ob` | web | auth | [routes/web.php](../../../routes/web.php):38 |
| POST | `/employee/ob/amend` | `OBController@amend_ob` **MISSING explicit method** | web | auth | [routes/web.php](../../../routes/web.php):39 |
| POST | `/employee/ob/cancel` | `OBController@cancel` | web | auth | [routes/web.php](../../../routes/web.php):40 |
| POST | `/employee/ob/approvedisapprove` | `OBController@approvedisapprove` | web | auth | [routes/web.php](../../../routes/web.php):41 |
| GET | `/employee/ob/print/{id}` | `OBController@print_ob` | web | auth | [routes/web.php](../../../routes/web.php):42 |
| GET | `/dtr-history` | `DTRController@history` | web | auth | [routes/web.php](../../../routes/web.php):44 |
| GET | `/dtr/time_log/{filter}` | `DTRController@time_log` | web | auth | [routes/web.php](../../../routes/web.php):45 |
| GET | `/dtr/dtr_analyzer` | `DTRController@dtr_analyzer` **MISSING explicit method** | web | auth | [routes/web.php](../../../routes/web.php):46 |
| POST | `/dtr/ssi_log_time` | `DTRController@log_time_ssi` | web | auth | [routes/web.php](../../../routes/web.php):47 |
| POST | `/dtr/ssi_log_time_wfh` | `DTRController@log_time_ssi_wfh` | web | auth | [routes/web.php](../../../routes/web.php):48 |
| GET | `/dtr/remarks/{month}-{year}` | `DTRController@dtr_remarks_index` | web | auth | [routes/web.php](../../../routes/web.php):49 |
| POST | `/dtr/remarks/edit` | `DTRController@get_dtr_remarks` | web | auth | [routes/web.php](../../../routes/web.php):50 |
| POST | `/dtr/remarks/delete` | `DTRController@delete_dtr_remarks` | web | auth | [routes/web.php](../../../routes/web.php):51 |
| POST | `/dtr/remarks/store` | `DTRController@save_remarks` | web | auth | [routes/web.php](../../../routes/web.php):52 |
| POST | `/leaves/submit` | `LeaveController@submit` | web | auth | [routes/web.php](../../../routes/web.php):54 |
| POST | `/leaves/check_dates` | `LeaveController@check_dates` | web | auth | [routes/web.php](../../../routes/web.php):55 |
| POST | `/leaves/delete` | `LeaveController@_delete` | web | auth | [routes/web.php](../../../routes/web.php):56 |
| POST | `/leaves/check_holiday` | `LeaveController@check_holiday` | web | auth | [routes/web.php](../../../routes/web.php):57 |
| POST | `/leaves/return_request` | `LeaveController@return_request` | web | auth | [routes/web.php](../../../routes/web.php):58 |
| GET | `/leaves/calendar` | `LeaveController@calendar` | web | auth | [routes/web.php](../../../routes/web.php):60 |
| POST | `/leaves/calendar/feed` | `LeaveController@feed_calendar` | web | auth | [routes/web.php](../../../routes/web.php):61 |
| POST | `/leaves/leave_details` | `LeaveController@leave_details` | web | auth | [routes/web.php](../../../routes/web.php):63 |
| POST | `/leaves/approve` | `LeaveController@approve` | web | auth | [routes/web.php](../../../routes/web.php):64 |
| POST | `/leaves/check_solo` | `LeaveController@check_solo` | web | auth | [routes/web.php](../../../routes/web.php):65 |
| POST | `/leaves/status_history` | `LeaveController@status_history` | web | auth | [routes/web.php](../../../routes/web.php):66 |
| POST | `/leaves/get_credits` | `LeaveController@get_credits` | web | auth | [routes/web.php](../../../routes/web.php):67 |
| POST | `/get_log` | `LeaveController@get_logs` | web | auth | [routes/web.php](../../../routes/web.php):68 |
| POST | `/leaves/log/delete` | `LeaveController@delete_leave_log` | web | auth | [routes/web.php](../../../routes/web.php):69 |
| POST | `/leaves/cancel_leave` | `LeaveController@cancel_leave` | web | auth | [routes/web.php](../../../routes/web.php):70 |
| GET | `/leaves/report/{leave_id}` | `LeaveController@leave_report` | web | auth | [routes/web.php](../../../routes/web.php):71 |
| GET | `/leaves/staff-credits` | `LeaveController@staff_leave_index` | web | auth | [routes/web.php](../../../routes/web.php):72 |
| POST | `/leaves/staff_credits/update` | `LeaveController@update_leave_credits` | web | auth | [routes/web.php](../../../routes/web.php):73 |
| POST | `/leaves/undo_cancellation` | `LeaveController@undo_cancellation` | web | auth | [routes/web.php](../../../routes/web.php):74 |
| POST | `/leaves/amend_date` | `LeaveController@amend_date` | web | auth | [routes/web.php](../../../routes/web.php):75 |
| GET | `/my-leave` | `LeaveController@index` | web | auth | [routes/web.php](../../../routes/web.php):77 |
| GET | `/leave-overview` | `LeaveController@overview_index` | web | auth | [routes/web.php](../../../routes/web.php):78 |
| GET | `/leave-overview-oic` | `LeaveController@overview_index_oic` | web | auth | [routes/web.php](../../../routes/web.php):79 |
| GET | `/employee/ot` | `OvertimeController@index` | web | auth | [routes/web.php](../../../routes/web.php):82 |
| GET | `/employee/ot/allstaff` | `OvertimeController@allstaff` | web | auth | [routes/web.php](../../../routes/web.php):83 |
| POST | `/employee/ot/file_ot` | `OvertimeController@file_ot` | web | auth | [routes/web.php](../../../routes/web.php):84 |
| POST | `/employee/ot/edit` | `OvertimeController@edit_ot` | web | auth | [routes/web.php](../../../routes/web.php):85 |
| POST | `/employee/ot/cancel` | `OvertimeController@cancel` | web | auth | [routes/web.php](../../../routes/web.php):86 |
| POST | `/employee/ot/check_dates` | `OvertimeController@check_dates` | web | auth | [routes/web.php](../../../routes/web.php):87 |
| POST | `/employee/ot/approvedisapprove` | `OvertimeController@approvedisapprove` | web | auth | [routes/web.php](../../../routes/web.php):88 |
| GET | `/employee/ot/print/{id}` | `OvertimeController@print_form` | web | auth | [routes/web.php](../../../routes/web.php):89 |
| GET | `/cto` | `CTOController@index` | web | auth | [routes/web.php](../../../routes/web.php):91 |
| GET | `/cto/allstaff` | `CTOController@allstaff` | web | auth | [routes/web.php](../../../routes/web.php):92 |
| POST | `/cto/file_cto` | `CTOController@file_cto` | web | auth | [routes/web.php](../../../routes/web.php):93 |
| GET | `/cto/staff-credits` | `CTOController@staff_index` | web | auth | [routes/web.php](../../../routes/web.php):94 |
| POST | `/cto/edit_cto` | `CTOController@edit_cto` | web | auth | [routes/web.php](../../../routes/web.php):95 |
| POST | `/cto/certify_cto` | `CTOController@certify_cto` | web | auth | [routes/web.php](../../../routes/web.php):96 |
| POST | `/cto/approvedisapprove` | `CTOController@approvedisapprove` | web | auth | [routes/web.php](../../../routes/web.php):97 |
| POST | `/cto/cancel` | `CTOController@cancel` | web | auth | [routes/web.php](../../../routes/web.php):98 |
| POST | `/cto/view_coc` | `CTOController@view_coc` | web | auth | [routes/web.php](../../../routes/web.php):99 |
| POST | `/cto/check_dates` | `CTOController@check_dates` | web | auth | [routes/web.php](../../../routes/web.php):100 |
| POST | `/cto/get_user` | `CTOController@get_user` | web | auth | [routes/web.php](../../../routes/web.php):101 |
| POST | `/cto/get_user_coc` | `CTOController@get_user_coc` | web | auth | [routes/web.php](../../../routes/web.php):102 |
| POST | `/cto/delete_coc_log` | `CTOController@delete_coc_log` | web | auth | [routes/web.php](../../../routes/web.php):103 |
| POST | `/cto/get_coc_log` | `CTOController@get_coc_logs` | web | auth | [routes/web.php](../../../routes/web.php):104 |
| POST | `/cto/save_coc_log` | `CTOController@save_coc_log` | web | auth | [routes/web.php](../../../routes/web.php):105 |
| GET | `/employee/cto/print/{id}` | `CTOController@print_cto` | web | auth | [routes/web.php](../../../routes/web.php):106 |
| GET | `/cto/check_coc` | `CTOController@check_coc` | web | auth | [routes/web.php](../../../routes/web.php):107 |
| POST | `/cto/get_info` | `CTOController@get_info` **MISSING explicit method** | web | auth | [routes/web.php](../../../routes/web.php):108 |
| POST | `/cto/update_hours` | `CTOController@update_hours` | web | auth | [routes/web.php](../../../routes/web.php):109 |
| POST | `/cto/status_history` | `CTOController@status_history` | web | auth | [routes/web.php](../../../routes/web.php):110 |
| GET | `/hr/settings` | `HRSettingsController@index` | web | auth | [routes/web.php](../../../routes/web.php):114 |
| POST | `/hr/settings/sg` | `HRSettingsController@sg_index` | web | auth | [routes/web.php](../../../routes/web.php):115 |
| POST | `/hr/settings/sg/save` | `HRSettingsController@save_sg` | web | auth | [routes/web.php](../../../routes/web.php):116 |
| POST | `/hr/settings/sg/delete` | `HRSettingsController@delete_sg` | web | auth | [routes/web.php](../../../routes/web.php):117 |
| POST | `/hr/settings/sg/get_sg` | `HRSettingsController@get_sg` | web | auth | [routes/web.php](../../../routes/web.php):118 |
| POST | `/hr/settings/table1` | `HRSettingsController@table1` | web | auth | [routes/web.php](../../../routes/web.php):120 |
| POST | `/hr/settings/table2` | `HRSettingsController@table2` | web | auth | [routes/web.php](../../../routes/web.php):121 |
| POST | `/hr/settings/table3` | `HRSettingsController@table3` | web | auth | [routes/web.php](../../../routes/web.php):122 |
| POST | `/hr/settings/table4` | `HRSettingsController@table4` | web | auth | [routes/web.php](../../../routes/web.php):123 |
| GET | `/hr/staff` | `HRStaffController@index` | web | auth | [routes/web.php](../../../routes/web.php):125 |
| POST | `/hr/staff/store` | `HRStaffController@store` | web | auth | [routes/web.php](../../../routes/web.php):126 |
| POST | `/hr/staff/check_email` | `HRStaffController@check_email` | web | auth | [routes/web.php](../../../routes/web.php):127 |
| POST | `/hr/staff/check_atms_id` | `HRStaffController@check_atms_id` | web | auth | [routes/web.php](../../../routes/web.php):128 |
| POST | `/hr/staff/edit` | `HRStaffController@edit` | web | auth | [routes/web.php](../../../routes/web.php):129 |
| POST | `/hr/staff/archive` | `HRStaffController@archive` | web | auth | [routes/web.php](../../../routes/web.php):130 |
| POST | `/hr/staff/uploads` | `HRStaffController@uploads` | web | auth | [routes/web.php](../../../routes/web.php):131 |
| POST | `/hr/staff/check_position` | `HRStaffController@check_position` | web | auth | [routes/web.php](../../../routes/web.php):132 |
| POST | `/hr/staff/sync-users` | `HRStaffController@syncUsersToSsi` | web | auth | [routes/web.php](../../../routes/web.php):133 |
| POST | `/hr/staff/oic` | `HRStaffController@setOIC` | web | auth | [routes/web.php](../../../routes/web.php):134 |
| GET | `/hr/payroll` | `HRPayrollController@index` | web | auth | [routes/web.php](../../../routes/web.php):136 |
| POST | `/hr/payroll/store` | `HRPayrollController@store` | web | auth | [routes/web.php](../../../routes/web.php):137 |
| POST | `/hr/payroll/edit` | `HRPayrollController@edit` | web | auth | [routes/web.php](../../../routes/web.php):138 |
| GET | `/hr/payroll` | `HRPayrollController@index` | web | auth | [routes/web.php](../../../routes/web.php):139 |
| GET | `/report/payroll/regular/{id}/{date}` | `HRPayrollController@reportPayrollRegular` | web | auth | [routes/web.php](../../../routes/web.php):140 |
| GET | `/report/payroll/regular/payslip/{id}/{date}` | `HRPayrollController@reportPayrollRegularPaySlip` | web | auth | [routes/web.php](../../../routes/web.php):141 |
| GET | `/report/payroll/csw-disbursement-voucher/{payroll_id}/{staff_id}` | `HRPayrollController@exportCswDisbursementVoucher` | web | auth | [routes/web.php](../../../routes/web.php):142 |
| GET | `/report/payroll/csw/{id}/{date}` | `HRPayrollController@export_csw_payroll` | web | auth | [routes/web.php](../../../routes/web.php):143 |
| GET | `/hr/payroll/staff/{payroll_id}/{period}` | `HRPayrollController@staff_index` | web | auth | [routes/web.php](../../../routes/web.php):145 |
| POST | `/hr/payroll/staff/store` | `HRPayrollController@staff_store` | web | auth | [routes/web.php](../../../routes/web.php):146 |
| POST | `/hr/payroll/staff/loan/store` | `HRPayrollController@loan_store` | web | auth | [routes/web.php](../../../routes/web.php):147 |
| POST | `/hr/payroll/staff/loan/delete` | `HRPayrollController@loan_delete` | web | auth | [routes/web.php](../../../routes/web.php):148 |
| POST | `/hr/payroll/staff/get_user_details` | `HRPayrollController@get_user_details` | web | auth | [routes/web.php](../../../routes/web.php):149 |
| POST | `/hr/payroll/staff/edit` | `HRPayrollController@staff_edit` | web | auth | [routes/web.php](../../../routes/web.php):150 |
| POST | `/hr/payroll/staff/delete` | `HRPayrollController@staff_delete` | web | auth | [routes/web.php](../../../routes/web.php):151 |
| POST | `/hr/payroll/staff/load_previous_payroll` | `HRPayrollController@load_previous_payroll` | web | auth | [routes/web.php](../../../routes/web.php):152 |
| GET | `/hr/payroll/print` | `HRPayrollController@printReport` **MISSING explicit method** | web | auth | [routes/web.php](../../../routes/web.php):153 |
| GET | `/ob-overview` | `OBController@overview_index` | web | auth | [routes/web.php](../../../routes/web.php):155 |
| GET | `/hr/overtime` | `OvertimeController@index_hr` | web | auth | [routes/web.php](../../../routes/web.php):156 |
| GET | `/cto-overview` | `CTOController@overview_index` | web | auth | [routes/web.php](../../../routes/web.php):157 |
| GET | `/cto-overview-oic` | `CTOController@overview_index_oic` | web | auth | [routes/web.php](../../../routes/web.php):158 |
| POST | `/hr/cto/override_cto` | `CTOController@override_cto` | web | auth | [routes/web.php](../../../routes/web.php):161 |
| POST | `/hr/ob/override_ob` | `OBController@override_ob` | web | auth | [routes/web.php](../../../routes/web.php):162 |
| POST | `/hr/leave/override_leave` | `LeaveController@override_leave` | web | auth | [routes/web.php](../../../routes/web.php):163 |
| POST | `/hr/get_workweek` | `ProfileController@get_workweek` | web | auth | [routes/web.php](../../../routes/web.php):164 |
| GET | `/dtr/report` | `HRStaffController@dtr_year` | web | auth | [routes/web.php](../../../routes/web.php):166 |
| GET | `/dtr/report/data` | `HRStaffController@dtrData` | web | auth | [routes/web.php](../../../routes/web.php):167 |
| GET | `/dtr/multi-report` | `HRStaffController@dtrReport_multi` | web | auth | [routes/web.php](../../../routes/web.php):168 |
| POST | `/dtr/multi-report/surname` | `HRStaffController@returnSurname` | web | auth | [routes/web.php](../../../routes/web.php):169 |
| GET | `/dtr/report/monthly/{month}-{year}` | `HRStaffController@monthlyDtrReport` | web | auth | [routes/web.php](../../../routes/web.php):170 |
| GET | `/dtr/datatable/monthly/{month}-{year}` | `HRStaffController@datatable_monthlyDtrReport` | web | auth | [routes/web.php](../../../routes/web.php):171 |
| GET | `/export/{month}-{year}` | `HRStaffController@export_monthlyDtrReport` | web | auth | [routes/web.php](../../../routes/web.php):172 |
| POST | `/dtr/report/individual` | `HRStaffController@print_my_dtr` | web | auth | [routes/web.php](../../../routes/web.php):173 |
| GET | `/dtr/update` | `HRStaffController@updateDTR` | web | auth | [routes/web.php](../../../routes/web.php):174 |
| GET | `/dtr/sync-dtr` | `HRStaffController@syncDTR` | web | auth | [routes/web.php](../../../routes/web.php):175 |
| GET | `/system-param/holidays` | `HolidayController@index` | web | none declared | [routes/web.php](../../../routes/web.php):177 |
| POST | `/system-parsam/holidays` | `HolidayController@saveHoliday` | web | none declared | [routes/web.php](../../../routes/web.php):178 |
| GET | `/get_tardiness` | `HolidayController@get_tardiness` | web | none declared | [routes/web.php](../../../routes/web.php):179 |
| GET | `/ssi/{calendar?}` | `AdminController@ssi` | web | auth | [routes/web.php](../../../routes/web.php):183 |
| GET | `/launch/dts` | `SsiController@launchDts` | web; auth | none declared | [routes/web.php](../../../routes/web.php):184 |
| POST | `/ssi/upload_gif` | `AdminController@upload_gif` | web | auth | [routes/web.php](../../../routes/web.php):187 |
| POST | `/ssi/load_bday_file` | `AdminController@load_bday_file` | web | auth | [routes/web.php](../../../routes/web.php):188 |
| POST | `/ssi/delete_bday_file` | `AdminController@delete_bday_file` | web | auth | [routes/web.php](../../../routes/web.php):189 |
| GET | `/wfh-accomplishments` | `WorkFromHomeAccomplishmentController@index` | web | auth | [routes/web.php](../../../routes/web.php):191 |
| POST | `/wfh-accomplishments/store` | `WorkFromHomeAccomplishmentController@store` | web | auth | [routes/web.php](../../../routes/web.php):192 |
| POST | `/wfh-accomplishments/update` | `WorkFromHomeAccomplishmentController@update` | web | auth | [routes/web.php](../../../routes/web.php):193 |
| POST | `/wfh-accomplishments/chief-update` | `WorkFromHomeAccomplishmentController@chiefUpdate` | web | auth | [routes/web.php](../../../routes/web.php):194 |
| GET | `/wfh-accomplishments/chief-available-staff` | `WorkFromHomeAccomplishmentController@chiefAvailableStaff` | web | auth | [routes/web.php](../../../routes/web.php):195 |
| POST | `/wfh-accomplishments/chief-store-staff` | `WorkFromHomeAccomplishmentController@chiefStoreForStaff` | web | auth | [routes/web.php](../../../routes/web.php):196 |
| POST | `/wfh-accomplishments/delete` | `WorkFromHomeAccomplishmentController@destroy` | web | auth | [routes/web.php](../../../routes/web.php):197 |
| POST | `/wfh-accomplishments/consolidated/delete` | `WorkFromHomeAccomplishmentController@deleteConsolidated` | web | auth | [routes/web.php](../../../routes/web.php):198 |
| POST | `/wfh-accomplishments/consolidated/reconsolidate` | `WorkFromHomeAccomplishmentController@reconsolidate` | web | auth | [routes/web.php](../../../routes/web.php):199 |
| POST | `/wfh-accomplishments/approve` | `WorkFromHomeAccomplishmentController@approveAccomplishment` | web | auth | [routes/web.php](../../../routes/web.php):200 |
| POST | `/wfh-accomplishments/return` | `WorkFromHomeAccomplishmentController@returnAccomplishment` | web | auth | [routes/web.php](../../../routes/web.php):201 |
| POST | `/wfh-accomplishments/consolidate` | `WorkFromHomeAccomplishmentController@consolidate` | web | auth | [routes/web.php](../../../routes/web.php):202 |
| POST | `/wfh-accomplishments/sign` | `WorkFromHomeAccomplishmentController@signConsolidated` | web | auth | [routes/web.php](../../../routes/web.php):203 |
| POST | `/wfh-accomplishments/head-approve` | `WorkFromHomeAccomplishmentController@approveConsolidated` | web | auth | [routes/web.php](../../../routes/web.php):204 |
| GET | `/wfh-accomplishments/print/division/{id}` | `WorkFromHomeAccomplishmentController@printDivision` | web | auth | [routes/web.php](../../../routes/web.php):205 |
| GET | `/wfh-accomplishments/print/agency/{date}` | `WorkFromHomeAccomplishmentController@printAgency` | web | auth | [routes/web.php](../../../routes/web.php):206 |
| POST | `/system-param/holidays/add` | `HolidayController@saveHoliday` | web | none declared | [routes/web.php](../../../routes/web.php):208 |
| POST | `/system-param/holidays/edit` | `HolidayController@editHoliday` | web | none declared | [routes/web.php](../../../routes/web.php):209 |
| POST | `/system-param/holidays/delete` | `HolidayController@delete_holiday` | web | none declared | [routes/web.php](../../../routes/web.php):210 |
| GET | `/admin/application` | `SsiController@application` | web | none declared | [routes/web.php](../../../routes/web.php):212 |
| GET | `/admin/user` | `SsiController@user` | web | none declared | [routes/web.php](../../../routes/web.php):213 |
| POST | `/staff/personnel-file` | `HRStaffController@dataSheetUPload` | web | auth | [routes/web.php](../../../routes/web.php):217 |
| GET | `/staff/datasheet/{id}` | `HRStaffController@showDatasheet` | web | auth | [routes/web.php](../../../routes/web.php):218 |
| GET | `/employee/uploads` | `PersonnelDataManagementController@index` | web | none declared | [routes/web.php](../../../routes/web.php):219 |
| POST | `/employee/uploads/add` | `PersonnelDataManagementController@upload` | web | none declared | [routes/web.php](../../../routes/web.php):220 |
| GET | `/hr/uploads` | `PersonnelDataManagementController@hr_index` | web | none declared | [routes/web.php](../../../routes/web.php):221 |
| POST | `/employee/uploads/cancel` | `PersonnelDataManagementController@cancel` | web | none declared | [routes/web.php](../../../routes/web.php):222 |
| POST | `/hr/uploads/hr_action` | `PersonnelDataManagementController@hr_action` | web | none declared | [routes/web.php](../../../routes/web.php):223 |
| POST | `/hr/uploads/display_year` | `PersonnelDataManagementController@display_year` | web | none declared | [routes/web.php](../../../routes/web.php):224 |
| GET | `/hr/recruitment` | `RecruitmentController@index` | web | auth | [routes/web.php](../../../routes/web.php):226 |
| GET | `/hr/filter` | `HRStaffController@filter` | web | auth | [routes/web.php](../../../routes/web.php):228 |
| GET | `/hr/dtr_info` | `HRStaffController@dtr_info` | web | auth | [routes/web.php](../../../routes/web.php):229 |
| GET | `/hr_staff/dtr/year-data` | `HRStaffController@getDtrYearData` | web | auth | [routes/web.php](../../../routes/web.php):230 |
| GET | `/positions` | `RecruitmentController@pos_index` | web | auth | [routes/web.php](../../../routes/web.php):233 |
| POST | `/hr/recruitment/positions` | `RecruitmentController@positions_index` | web | auth | [routes/web.php](../../../routes/web.php):234 |
| POST | `/hr/recruitment/positions/delete` | `RecruitmentController@delete_positions` | web | auth | [routes/web.php](../../../routes/web.php):235 |
| POST | `/hr/recruitment/positions/get_positions` | `RecruitmentController@get_positions` | web | auth | [routes/web.php](../../../routes/web.php):236 |
| POST | `/hr/recruitment/positions/save` | `RecruitmentController@save_positions` | web | auth | [routes/web.php](../../../routes/web.php):237 |
| POST | `/hr/recruitment/categories` | `RecruitmentController@category_index` | web | auth | [routes/web.php](../../../routes/web.php):239 |
| POST | `/hr/recruitment/categories/delete` | `RecruitmentController@delete_category` | web | auth | [routes/web.php](../../../routes/web.php):240 |
| POST | `/hr/recruitment/categories/get_category` | `RecruitmentController@get_category` | web | auth | [routes/web.php](../../../routes/web.php):241 |
| POST | `/hr/recruitment/categories/save` | `RecruitmentController@save_category` | web | auth | [routes/web.php](../../../routes/web.php):242 |
| POST | `/hr/recruitment/questions/question_count` | `RecruitmentController@question_count` | web | auth | [routes/web.php](../../../routes/web.php):244 |
| POST | `/hr/recruitment/load_questions` | `RecruitmentController@load_questions` | web | auth | [routes/web.php](../../../routes/web.php):245 |
| POST | `/hr/recruitment/questions/delete` | `RecruitmentController@delete_question` | web | auth | [routes/web.php](../../../routes/web.php):247 |
| POST | `/hr/save_question` | `RecruitmentController@save_question` | web | auth | [routes/web.php](../../../routes/web.php):248 |
| POST | `/hr/recruitment/load_choices` | `RecruitmentController@load_choices` | web | auth | [routes/web.php](../../../routes/web.php):250 |
| POST | `/hr/recruitment/delete_choice` | `RecruitmentController@delete_choice` | web | auth | [routes/web.php](../../../routes/web.php):251 |
| POST | `/hr/save_choice` | `RecruitmentController@save_choice` | web | auth | [routes/web.php](../../../routes/web.php):252 |
| POST | `/hr/recruitment/load_profile` | `RecruitmentController@load_profile` | web | auth | [routes/web.php](../../../routes/web.php):254 |
| POST | `/hr/save_profile` | `RecruitmentController@save_profile` | web | auth | [routes/web.php](../../../routes/web.php):255 |
| POST | `/hr/recruitment/delete_profile` | `RecruitmentController@delete_profile` | web | auth | [routes/web.php](../../../routes/web.php):256 |
| POST | `/hr/recruitment/load_applications` | `RecruitmentController@load_applications` | web | auth | [routes/web.php](../../../routes/web.php):258 |
| POST | `/hr/recruitment/application_dropdown` | `RecruitmentController@load_applications_dropdown` | web | auth | [routes/web.php](../../../routes/web.php):259 |
| POST | `/hr/save_application` | `RecruitmentController@save_application` | web | auth | [routes/web.php](../../../routes/web.php):260 |
| POST | `/hr/upload_requirements` | `RecruitmentController@upload_requirements` | web | auth | [routes/web.php](../../../routes/web.php):261 |
| POST | `/hr/upload_eval_docs` | `RecruitmentController@upload_eval_docs` | web | auth | [routes/web.php](../../../routes/web.php):262 |
| POST | `/hr/recruitment/get_application` | `RecruitmentController@get_application` | web | auth | [routes/web.php](../../../routes/web.php):266 |
| POST | `/hr/recruitment/requirements` | `RecruitmentController@requirements` | web | auth | [routes/web.php](../../../routes/web.php):267 |
| POST | `/hr/recruitment/delete_uploads` | `RecruitmentController@delete_uploads` | web | auth | [routes/web.php](../../../routes/web.php):268 |
| POST | `/hr/recruitment/delete_application` | `RecruitmentController@delete_application` | web | auth | [routes/web.php](../../../routes/web.php):269 |
| POST | `/hr/recruitment/eval_docs` | `RecruitmentController@eval_docs` | web | auth | [routes/web.php](../../../routes/web.php):271 |
| POST | `/hr/recruitment/save_questionnaire` | `RecruitmentController@save_questionnaire` | web | auth | [routes/web.php](../../../routes/web.php):274 |
| POST | `/hr/recruitment/load_questionnaire` | `RecruitmentController@load_questionnaire` | web | auth | [routes/web.php](../../../routes/web.php):275 |
| POST | `/hr/recruitment/delete_questionnaire` | `RecruitmentController@delete_questionnaire` | web | auth | [routes/web.php](../../../routes/web.php):276 |
| GET | `/careers` | `RecruitmentExamController@applicant_index` | web | auth | [routes/web.php](../../../routes/web.php):278 |
| GET | `/careers/my_applications` | `RecruitmentExamController@my_applications` | web | auth | [routes/web.php](../../../routes/web.php):279 |
| POST | `/careers/upload_files` | `RecruitmentExamController@upload_files` **MISSING explicit method** | web | auth | [routes/web.php](../../../routes/web.php):280 |
| POST | `/toggle` | `RecruitmentController@toggle` | web | auth | [routes/web.php](../../../routes/web.php):281 |
| GET | `/careers/exam/{category_id}` | `RecruitmentExamController@exam` | web | auth | [routes/web.php](../../../routes/web.php):282 |
| POST | `/careers/submit_exam` | `RecruitmentExamController@submit_exam` | web | auth | [routes/web.php](../../../routes/web.php):284 |
| POST | `/careers/start_timer` | `RecruitmentExamController@start_timer` | web | auth | [routes/web.php](../../../routes/web.php):285 |
| GET | `/hr/recognition-awardees` | `HRStaffController@nominationAwardees` | web | auth | [routes/web.php](../../../routes/web.php):287 |
| POST | `/hr/recognition-awardees` | `HRStaffController@nominationAwardeesSave` | web | auth | [routes/web.php](../../../routes/web.php):288 |
| POST | `/hr/recognition-awardees/awardees` | `HRStaffController@nominationAwardees` | web | auth | [routes/web.php](../../../routes/web.php):289 |
| POST | `/hr/recognition-awardees/show` | `HRStaffController@nominationAwardeesShow` **MISSING explicit method** | web | auth | [routes/web.php](../../../routes/web.php):290 |
| POST | `/hr/recognition-awardees/delete` | `HRStaffController@nominationAwardeesDelete` | web | auth | [routes/web.php](../../../routes/web.php):291 |
| POST | `/hr/recognition-awardees/vote` | `HRStaffController@nominationAwardeesVote` | web | auth | [routes/web.php](../../../routes/web.php):292 |
| POST | `/hr/recognition-awardees/attreport` | `HRStaffController@attendanceReport` | web | auth | [routes/web.php](../../../routes/web.php):293 |
| POST | `/hr/submit_application` | `RecruitmentController@submit_application` | web | auth | [routes/web.php](../../../routes/web.php):295 |
| GET | `/hr/attendance_record` | `DTRController@attendance_record` | web | auth | [routes/web.php](../../../routes/web.php):297 |
| GET | `/careers/profile` | `RecruitmentExamController@profile_index` | web | auth | [routes/web.php](../../../routes/web.php):300 |
| POST | `/careers/profile/update_profile` | `RecruitmentExamController@save_profile` | web | auth | [routes/web.php](../../../routes/web.php):301 |
| POST | `/careers/profile/upload_profile` | `RecruitmentExamController@profile_uploads` | web | auth | [routes/web.php](../../../routes/web.php):302 |
| GET | `/recruitment/exam_answers/{application_id}` | `RecruitmentController@exam_answers` | web | auth | [routes/web.php](../../../routes/web.php):304 |
| POST | `/hr/recruitment/load_evaluators` | `RecruitmentController@load_evaluators` | web | auth | [routes/web.php](../../../routes/web.php):306 |
| POST | `/hr/recruitment/get_evaluators` | `RecruitmentController@get_evaluators` | web | auth | [routes/web.php](../../../routes/web.php):307 |
| POST | `/hr/recruitment/delete_evaluator` | `RecruitmentController@delete_evaluator` | web | auth | [routes/web.php](../../../routes/web.php):308 |
| POST | `/hr/recruitment/save_evaluator` | `RecruitmentController@save_evaluator` | web | auth | [routes/web.php](../../../routes/web.php):309 |
| GET | `/ssi/login/{token}` | `Auth\LoginController@ssiLogin` | web | guest->except('logout') | [routes/web.php](../../../routes/web.php):311 |
| GET | `/test` | `APIController@test` | web | none declared | [routes/web.php](../../../routes/web.php):313 |
| POST | `/generateMultiDTR` | `HRStaffController@multipleMothlyReport` | web | auth | [routes/web.php](../../../routes/web.php):315 |
| POST | `/save_event` | `HRStaffController@save_event` | web | auth | [routes/web.php](../../../routes/web.php):316 |
| POST | `/delete_event` | `SsiController@delete_event` | web | none declared | [routes/web.php](../../../routes/web.php):317 |
| POST | `/get_dtr` | `DTRController@get_dtr` | web | auth | [routes/web.php](../../../routes/web.php):318 |
| POST | `/save_override` | `DTRController@save_override` | web | auth | [routes/web.php](../../../routes/web.php):319 |
| POST | `/check_record` | `DTRController@check_record` | web | auth | [routes/web.php](../../../routes/web.php):320 |
| GET | `/dtr/summary` | `DTRController@getSummary` | web | auth | [routes/web.php](../../../routes/web.php):321 |
| GET | `/profile` | `ProfileController@index` | web | auth | [routes/web.php](../../../routes/web.php):323 |
| GET | `/profile/pds_form` | `ProfileController@pds_form` | web | auth | [routes/web.php](../../../routes/web.php):324 |
| GET | `/profile/pds_file/{id}` | `ProfileController@pds_file` | web | auth | [routes/web.php](../../../routes/web.php):325 |
| GET | `/profile/personal_info` | `ProfileController@personal_info_index` | web | auth | [routes/web.php](../../../routes/web.php):326 |
| GET | `/profile/family` | `ProfileController@family_bg_index` | web | auth | [routes/web.php](../../../routes/web.php):327 |
| GET | `/profile/education` | `ProfileController@educ_bg_index` | web | auth | [routes/web.php](../../../routes/web.php):328 |
| GET | `/profile/eligibility` | `ProfileController@civil_service_index` | web | auth | [routes/web.php](../../../routes/web.php):329 |
| GET | `/profile/experience` | `ProfileController@work_ex_index` | web | auth | [routes/web.php](../../../routes/web.php):330 |
| GET | `/profile/voluntary_work` | `ProfileController@voluntary_work_index` | web | auth | [routes/web.php](../../../routes/web.php):331 |
| GET | `/profile/learning_and_development` | `ProfileController@learning_and_dev_index` | web | auth | [routes/web.php](../../../routes/web.php):332 |
| GET | `/profile/other_information` | `ProfileController@other_info_index` | web | auth | [routes/web.php](../../../routes/web.php):333 |
| GET | `/profile/govt_declarations` | `ProfileController@govt_declarations` | web | auth | [routes/web.php](../../../routes/web.php):334 |
| GET | `/profile/character_reference` | `ProfileController@character_reference` | web | auth | [routes/web.php](../../../routes/web.php):335 |
| GET | `/profile/govt_id` | `ProfileController@govt_id` | web | auth | [routes/web.php](../../../routes/web.php):336 |
| GET | `/profile/positions_history` | `ProfileController@positions_history_index` | web | auth | [routes/web.php](../../../routes/web.php):337 |
| POST | `/profile/save_account` | `ProfileController@save_account` | web | auth | [routes/web.php](../../../routes/web.php):339 |
| POST | `/profile/save_personal` | `ProfileController@save_personal` | web | auth | [routes/web.php](../../../routes/web.php):340 |
| POST | `/profile/save_fam` | `ProfileController@save_fam` | web | auth | [routes/web.php](../../../routes/web.php):341 |
| POST | `/profile/save_educ` | `ProfileController@save_educ` | web | auth | [routes/web.php](../../../routes/web.php):342 |
| POST | `/profile/delete_educ` | `ProfileController@delete_educ` | web | auth | [routes/web.php](../../../routes/web.php):343 |
| POST | `/profile/save_eligibility` | `ProfileController@save_eligibility` | web | auth | [routes/web.php](../../../routes/web.php):344 |
| POST | `/profile/delete_eligibility` | `ProfileController@delete_eligibility` | web | auth | [routes/web.php](../../../routes/web.php):345 |
| POST | `/profile/save_workex` | `ProfileController@save_workex` | web | auth | [routes/web.php](../../../routes/web.php):346 |
| POST | `/profile/save_present` | `ProfileController@save_present` | web | auth | [routes/web.php](../../../routes/web.php):347 |
| POST | `/profile/delete_workex` | `ProfileController@delete_workex` | web | auth | [routes/web.php](../../../routes/web.php):348 |
| POST | `/profile/save_vol_work` | `ProfileController@save_vol_work` | web | auth | [routes/web.php](../../../routes/web.php):349 |
| POST | `/profile/delete_vol_work` | `ProfileController@delete_vol_work` | web | auth | [routes/web.php](../../../routes/web.php):350 |
| POST | `/profile/save_ld` | `ProfileController@save_ld` | web | auth | [routes/web.php](../../../routes/web.php):351 |
| POST | `/profile/upload_cert` | `ProfileController@upload_cert` | web | auth | [routes/web.php](../../../routes/web.php):352 |
| POST | `/profile/training_details` | `ProfileController@training_details` | web | auth | [routes/web.php](../../../routes/web.php):353 |
| POST | `/profile/updatetraining_details` | `ProfileController@updatetraining_details` | web | auth | [routes/web.php](../../../routes/web.php):354 |
| POST | `/profile/educ_details` | `ProfileController@educ_details` | web | auth | [routes/web.php](../../../routes/web.php):361 |
| POST | `/profile/updateeduc_details` | `ProfileController@updateeduc_details` | web | auth | [routes/web.php](../../../routes/web.php):362 |
| POST | `/profile/elig_details` | `ProfileController@elig_details` | web | auth | [routes/web.php](../../../routes/web.php):364 |
| POST | `/profile/updateelig_details` | `ProfileController@updateelig_details` | web | auth | [routes/web.php](../../../routes/web.php):365 |
| POST | `/profile/work_details` | `ProfileController@work_details` | web | auth | [routes/web.php](../../../routes/web.php):367 |
| POST | `/profile/updatework_details` | `ProfileController@updatework_details` | web | auth | [routes/web.php](../../../routes/web.php):368 |
| POST | `/profile/vol_details` | `ProfileController@vol_details` | web | auth | [routes/web.php](../../../routes/web.php):370 |
| POST | `/profile/updatevol_details` | `ProfileController@updatevol_details` | web | auth | [routes/web.php](../../../routes/web.php):371 |
| POST | `/profile/other_details` | `ProfileController@other_details` | web | auth | [routes/web.php](../../../routes/web.php):373 |
| POST | `/profile/updateother_details` | `ProfileController@updateother_details` | web | auth | [routes/web.php](../../../routes/web.php):374 |
| POST | `/profile/delete_ld` | `ProfileController@delete_ld` | web | auth | [routes/web.php](../../../routes/web.php):377 |
| POST | `/profile/save_other_info` | `ProfileController@save_other_info` | web | auth | [routes/web.php](../../../routes/web.php):378 |
| POST | `/profile/delete_other_info` | `ProfileController@delete_other_info` | web | auth | [routes/web.php](../../../routes/web.php):379 |
| POST | `/profile/save_gov_dec` | `ProfileController@save_gov_dec` | web | auth | [routes/web.php](../../../routes/web.php):380 |
| POST | `/profile/save_char_ref` | `ProfileController@save_char_ref` | web | auth | [routes/web.php](../../../routes/web.php):381 |
| POST | `/profile/save_gov_id` | `ProfileController@save_gov_id` | web | auth | [routes/web.php](../../../routes/web.php):382 |
| GET | `/set_all_unsetted_password` | `AdminController@reset_all_password` | web | auth | [routes/web.php](../../../routes/web.php):388 |
| GET | `/psrti_calendar` | `SsiController@psrti_calendar` | web | none declared | [routes/web.php](../../../routes/web.php):392 |
| GET | `/leave_calendar` | `SsiController@leave_calendar` | web | none declared | [routes/web.php](../../../routes/web.php):393 |
| GET | `/my_calendar` | `SsiController@my_calendar` | web | none declared | [routes/web.php](../../../routes/web.php):394 |
| GET | `/ed_sched` | `SsiController@ed_sched` | web | none declared | [routes/web.php](../../../routes/web.php):395 |
| GET | `/events` | `SsiController@events` | web | none declared | [routes/web.php](../../../routes/web.php):396 |
| GET | `/my-calendar` | `SsiController@my_calendar_index` | web | none declared | [routes/web.php](../../../routes/web.php):397 |
| POST | `/events/save` | `SsiController@save_event` | web | none declared | [routes/web.php](../../../routes/web.php):398 |
| POST | `/get_event` | `SsiController@get_event` | web | none declared | [routes/web.php](../../../routes/web.php):399 |
| POST | `/get_ob` | `SsiController@get_ob` | web | none declared | [routes/web.php](../../../routes/web.php):400 |
| POST | `/get_leave` | `SsiController@get_leave` | web | none declared | [routes/web.php](../../../routes/web.php):401 |
| POST | `/get_cto` | `SsiController@get_cto` | web | none declared | [routes/web.php](../../../routes/web.php):402 |
| POST | `/event_description` | `SsiController@event_description` | web | none declared | [routes/web.php](../../../routes/web.php):403 |
| POST | `/dtr/delete_event` | `HRStaffController@delete_event` | web | auth | [routes/web.php](../../../routes/web.php):404 |
| GET | `/links` | `SsiController@link_index` | web | none declared | [routes/web.php](../../../routes/web.php):406 |
| POST | `/addLink/create` | `SsiController@add_link` | web | none declared | [routes/web.php](../../../routes/web.php):407 |
| POST | `/linkUpdate` | `SsiController@linkUpdate` | web | none declared | [routes/web.php](../../../routes/web.php):408 |
| DELETE | `/removeLink/{id}` | `SsiController@removeLink` | web | none declared | [routes/web.php](../../../routes/web.php):409 |
| POST | `/wellness/store` | `SsiController@store_wellness` | web | none declared | [routes/web.php](../../../routes/web.php):410 |
| POST | `/wellness/update` | `SsiController@wellnessUpdate` | web | none declared | [routes/web.php](../../../routes/web.php):411 |
| DELETE | `/wellness/delete/{id}` | `SsiController@delete_wellness` | web | none declared | [routes/web.php](../../../routes/web.php):412 |
| POST | `/application` | `SsiController@applicationFeatures` | web | none declared | [routes/web.php](../../../routes/web.php):415 |
| POST | `/ssi/toggle-status` | `SsiController@toggleStatus` | web | none declared | [routes/web.php](../../../routes/web.php):416 |
| POST | `/applicationUpdate` | `SsiController@applicationUpdate` | web | none declared | [routes/web.php](../../../routes/web.php):417 |
| GET | `/announcements` | `SsiController@announcements` | web | none declared | [routes/web.php](../../../routes/web.php):419 |
| POST | `/announcement/save` | `SsiController@save_announcement` | web | none declared | [routes/web.php](../../../routes/web.php):420 |
| POST | `/delete_announcement` | `SsiController@delete_announcement` | web | none declared | [routes/web.php](../../../routes/web.php):421 |
| GET | `/test-upload` | `SsiController@test_upload` | web | none declared | [routes/web.php](../../../routes/web.php):424 |
| POST | `/test-upload/submit` | `SsiController@handle_test_upload` | web | none declared | [routes/web.php](../../../routes/web.php):425 |
| GET | `/data_check_for_tardy` | `HRStaffController@deduct_tardy_to_credits` | web | auth | [routes/web.php](../../../routes/web.php):428 |
| GET | `/leave_template` | `LeaveController@leave_template` | web | auth | [routes/web.php](../../../routes/web.php):429 |
| GET | `/all-time-log/data` | `AdminController@allGateLog` | web | auth | [routes/web.php](../../../routes/web.php):430 |
| GET | `/wfh-logs/data` | `AdminController@wfhLogsData` | web | auth | [routes/web.php](../../../routes/web.php):431 |
| GET | `/user_filter` | `AdminController@user_filter` | web | auth | [routes/web.php](../../../routes/web.php):432 |
| GET | `/my_gatelog` | `AdminController@myGateLog` | web | auth | [routes/web.php](../../../routes/web.php):435 |
| POST | `/announcement/bday` | `AdminController@bday` **MISSING explicit method** | web | auth | [routes/web.php](../../../routes/web.php):437 |
| GET | `/AnalyzeTimeLog` | `CronController@AnalyzeTimeLog` | web | none declared | [routes/web.php](../../../routes/web.php):440 |
| GET | `/GenerateLeaveCredit` | `CronController@GenerateLeaveCredit` | web | none declared | [routes/web.php](../../../routes/web.php):441 |
| GET | `/DeductLeaveCredit` | `CronController@DeductLeaveCredit` | web | none declared | [routes/web.php](../../../routes/web.php):442 |
| GET | `/sendBirthdayGreet` | `CronController@sendBirthdayGreet` | web | none declared | [routes/web.php](../../../routes/web.php):443 |
| GET | `/ApproveAFL` | `CronController@ApproveAFL` | web | none declared | [routes/web.php](../../../routes/web.php):444 |
| GET | `/AddWorkWeek` | `DTRController@AddWorkWeek` | web | auth | [routes/web.php](../../../routes/web.php):445 |
| GET | `/AddTardiness` | `DTRController@AddTardiness` | web | auth | [routes/web.php](../../../routes/web.php):446 |
| GET | `/BestAttendance` | `DTRController@BestAttendance` | web | auth | [routes/web.php](../../../routes/web.php):447 |
| GET | `/checkHolidays` | `DTRController@checkHolidays` | web | auth | [routes/web.php](../../../routes/web.php):448 |
| GET | `/DeductLeaveCredit` | `HRStaffController@deduct_tardy_to_credits` | web | auth | [routes/web.php](../../../routes/web.php):450 |
| GET | `/workSuspensionModify` | `DTRController@workSuspensionModify` | web | auth | [routes/web.php](../../../routes/web.php):452 |
| GET | `/specialOrderModify` | `DTRController@specialOrderModify` | web | auth | [routes/web.php](../../../routes/web.php):453 |
| GET | `/checkOB` | `DTRController@checkOB` | web | auth | [routes/web.php](../../../routes/web.php):454 |
| GET | `/checkCTO` | `DTRController@checkCTO` | web | auth | [routes/web.php](../../../routes/web.php):455 |
| GET | `/modifyDTRLeave` | `DTRController@modifyDTRLeave` | web | auth | [routes/web.php](../../../routes/web.php):456 |
| GET | `/applyPendingCocDeductions` | `CronController@applyPendingCocDeductions` | web | none declared | [routes/web.php](../../../routes/web.php):459 |
| GET | `/update_filled_position` | `CronController@update_filled_positions_status` | web | none declared | [routes/web.php](../../../routes/web.php):462 |
| GET | `/LnD` | `LearnAndDevController@index` | web | auth | [routes/web.php](../../../routes/web.php):466 |
| GET | `/LnD/datatable` | `LearnAndDevController@getDatatableData` | web | auth | [routes/web.php](../../../routes/web.php):467 |
| GET | `/LnD/staff/{pos_id}` | `LearnAndDevController@view` | web | auth | [routes/web.php](../../../routes/web.php):468 |
| GET | `/LnD/position-matrix/{pos_id}` | `LearnAndDevController@getPositionMatrix` | web | auth | [routes/web.php](../../../routes/web.php):469 |
| GET | `/LnD/matrix/{pos_id}` | `LearnAndDevController@matrix` | web | auth | [routes/web.php](../../../routes/web.php):470 |
| POST | `/LnD/matrix/get` | `LearnAndDevController@get_matrix` | web | auth | [routes/web.php](../../../routes/web.php):471 |
| POST | `/LnD/matrix/save` | `LearnAndDevController@save_matrix` | web | auth | [routes/web.php](../../../routes/web.php):472 |
| POST | `/LnD/matrix/copy` | `LearnAndDevController@save_copied_matrix` | web | auth | [routes/web.php](../../../routes/web.php):473 |
| POST | `/LnD/matrix/delete` | `LearnAndDevController@delete` | web | auth | [routes/web.php](../../../routes/web.php):474 |
| GET | `/print-table-matrix/{pos_id}` | `LearnAndDevController@print_staff_matrix` | web | auth | [routes/web.php](../../../routes/web.php):475 |
| GET | `/division-LnD` | `LearnAndDevController@division_lnd_index` | web | auth | [routes/web.php](../../../routes/web.php):477 |
| POST | `/division-lnd/create-plan` | `LearnAndDevController@create_division_plan` | web | auth | [routes/web.php](../../../routes/web.php):478 |
| GET | `/division-lnd/load-plan/{plan_id}` | `LearnAndDevController@load_division_plan` | web | auth | [routes/web.php](../../../routes/web.php):479 |
| POST | `/division-lnd/save-budgets` | `LearnAndDevController@save_division_budgets` | web | auth | [routes/web.php](../../../routes/web.php):480 |
| POST | `/division-lnd/delete-plan` | `LearnAndDevController@delete_division_plan` | web | auth | [routes/web.php](../../../routes/web.php):481 |
| GET | `/division-lnd/print/{plan_id}` | `LearnAndDevController@print_division_plan` | web | auth | [routes/web.php](../../../routes/web.php):482 |
| GET | `/division-lnd/print-range` | `LearnAndDevController@print_division_plan_range` | web | auth | [routes/web.php](../../../routes/web.php):483 |
| GET | `/import` | `LearnAndDevController@importIndex` | web | auth | [routes/web.php](../../../routes/web.php):487 |
| POST | `/import-training-data` | `LearnAndDevController@import` | web | auth | [routes/web.php](../../../routes/web.php):488 |
| GET | `/idp` | `IDPController@index` | web | auth | [routes/web.php](../../../routes/web.php):491 |
| POST | `/idp/store` | `IDPController@store` | web | auth | [routes/web.php](../../../routes/web.php):492 |
| GET | `/idp/idp_form/{id}` | `IDPController@idp_form` | web | auth | [routes/web.php](../../../routes/web.php):493 |
| GET | `/idp/get-idp/{id}` | `IDPController@get_idp` | web | auth | [routes/web.php](../../../routes/web.php):494 |
| GET | `/idp/get-idp-with-matrix/{id}` | `IDPController@get_idp_with_matrix` | web | auth | [routes/web.php](../../../routes/web.php):495 |
| POST | `/idp/submit-to-dc` | `IDPController@submit_to_dc` | web | auth | [routes/web.php](../../../routes/web.php):496 |
| POST | `/idp/sign-dc` | `IDPController@sign_dc` | web | auth | [routes/web.php](../../../routes/web.php):497 |
| POST | `/idp/delete` | `IDPController@delete` | web | auth | [routes/web.php](../../../routes/web.php):498 |
| POST | `/idp/get-trainings` | `IDPController@get_trainings` | web | auth | [routes/web.php](../../../routes/web.php):499 |
| GET | `/saln` | `SALNController@index` | web | auth | [routes/web.php](../../../routes/web.php):502 |
| POST | `/saln/add_record` | `SALNController@add_record` | web | auth | [routes/web.php](../../../routes/web.php):503 |
| GET | `/saln/view/{id}` | `SALNController@saln_form` | web | auth | [routes/web.php](../../../routes/web.php):505 |
| GET | `/saln/edit/{id}` | `SALNController@edit_saln` | web | auth | [routes/web.php](../../../routes/web.php):506 |
| GET | `/saln/declarant_info/{id}` | `SALNController@declarant_info` | web | auth | [routes/web.php](../../../routes/web.php):508 |
| POST | `/saln/save_declarant_info` | `SALNController@save_declarant_info` | web | auth | [routes/web.php](../../../routes/web.php):509 |
| GET | `/saln/children_info/{id}` | `SALNController@children_info` | web | auth | [routes/web.php](../../../routes/web.php):511 |
| POST | `/saln/save_child_info` | `SALNController@save_child_info` | web | auth | [routes/web.php](../../../routes/web.php):512 |
| POST | `/saln/delete_child_info` | `SALNController@delete_child_info` | web | auth | [routes/web.php](../../../routes/web.php):513 |
| GET | `/saln/assets_real/{id}` | `SALNController@assets_real` | web | auth | [routes/web.php](../../../routes/web.php):515 |
| POST | `/saln/save_real_asset` | `SALNController@save_real_asset` | web | auth | [routes/web.php](../../../routes/web.php):516 |
| POST | `/saln/delete_real_asset` | `SALNController@delete_real_asset` | web | auth | [routes/web.php](../../../routes/web.php):517 |
| GET | `/saln/assets_personal/{id}` | `SALNController@assets_personal` | web | auth | [routes/web.php](../../../routes/web.php):519 |
| POST | `/saln/save_personal_asset` | `SALNController@save_personal_asset` | web | auth | [routes/web.php](../../../routes/web.php):520 |
| POST | `/saln/delete_personal_asset` | `SALNController@delete_personal_asset` | web | auth | [routes/web.php](../../../routes/web.php):521 |
| GET | `/saln/liabilities/{id}` | `SALNController@liabilities` | web | auth | [routes/web.php](../../../routes/web.php):523 |
| POST | `/saln/save_liability` | `SALNController@save_liability` | web | auth | [routes/web.php](../../../routes/web.php):524 |
| POST | `/saln/delete_liability` | `SALNController@delete_liability` | web | auth | [routes/web.php](../../../routes/web.php):525 |
| GET | `/saln/business_interests/{id}` | `SALNController@business_interests` | web | auth | [routes/web.php](../../../routes/web.php):527 |
| POST | `/saln/save_business_interest` | `SALNController@save_business_interest` | web | auth | [routes/web.php](../../../routes/web.php):528 |
| POST | `/saln/delete_business_interest` | `SALNController@delete_business_interest` | web | auth | [routes/web.php](../../../routes/web.php):529 |
| GET | `/saln/govt_relatives/{id}` | `SALNController@govt_relatives` | web | auth | [routes/web.php](../../../routes/web.php):531 |
| POST | `/saln/save_govt_relative` | `SALNController@save_govt_relative` | web | auth | [routes/web.php](../../../routes/web.php):532 |
| POST | `/saln/delete_govt_relative` | `SALNController@delete_govt_relative` | web | auth | [routes/web.php](../../../routes/web.php):533 |
| GET | `/concerns` | `ConcernController@index` | web | auth | [routes/web.php](../../../routes/web.php):535 |
| POST | `/concerns/store` | `ConcernController@store` | web | auth | [routes/web.php](../../../routes/web.php):536 |
| POST | `/concerns/upload-file` | `ConcernController@uploadFiles` | web | auth | [routes/web.php](../../../routes/web.php):537 |
| POST | `/concern/remove-file` | `ConcernController@removeFile` | web | auth | [routes/web.php](../../../routes/web.php):538 |
| POST | `/getConcern` | `ConcernController@getConcern` | web | auth | [routes/web.php](../../../routes/web.php):539 |
| POST | `/getConcernUpdate` | `ConcernController@getConcernUpdate` | web | auth | [routes/web.php](../../../routes/web.php):540 |
| DELETE | `/cancelConcern/{id}` | `ConcernController@destroy` | web | auth | [routes/web.php](../../../routes/web.php):541 |
| POST | `/acknowledgeConcern` | `ConcernController@acknowledgeConcernUpdate` | web | auth | [routes/web.php](../../../routes/web.php):542 |
| GET | `/cert` | `COEController@index` | web | none declared | [routes/web.php](../../../routes/web.php):544 |
| GET | `/cert/hr` | `COEController@cert_hr_index` | web | none declared | [routes/web.php](../../../routes/web.php):545 |
| POST | `/cert/request` | `COEController@request` | web | none declared | [routes/web.php](../../../routes/web.php):546 |
| POST | `/cert/received` | `COEController@received` | web | none declared | [routes/web.php](../../../routes/web.php):547 |
| POST | `/cert/acknowledged` | `COEController@acknowledged` | web | none declared | [routes/web.php](../../../routes/web.php):548 |
| POST | `/cert/create` | `COEController@create` | web | none declared | [routes/web.php](../../../routes/web.php):549 |
| POST | `/getCertReceivedUpdate` | `COEController@getCertReceivedUpdate` | web | none declared | [routes/web.php](../../../routes/web.php):550 |
| POST | `/getCert` | `COEController@getCert` | web | none declared | [routes/web.php](../../../routes/web.php):551 |
| POST | `/getCertAcknowledgedUpdate` | `COEController@getCertAcknowledgedUpdate` | web | none declared | [routes/web.php](../../../routes/web.php):552 |
| POST | `/getCreateCert` | `COEController@getCreateCert` | web | none declared | [routes/web.php](../../../routes/web.php):553 |
| POST | `/cert/upload-file` | `COEController@uploadFile` | web | none declared | [routes/web.php](../../../routes/web.php):554 |
| GET | `/cert/pdf/{id}` | `COEController@employedGeneratePDF` | web | none declared | [routes/web.php](../../../routes/web.php):555 |
| GET | `/verification/{request_id}` | `COEController@verification` | web | none declared | [routes/web.php](../../../routes/web.php):556 |
| GET | `/404` | `COEController@notFound` **MISSING explicit method** | web | none declared | [routes/web.php](../../../routes/web.php):557 |
| GET | `/cert/manual` | `COEController@manual_index` | web | none declared | [routes/web.php](../../../routes/web.php):559 |
| POST | `/getEmployee` | `COEController@getEmployee` | web | none declared | [routes/web.php](../../../routes/web.php):560 |
| POST | `/cert/manualrequest` | `COEController@manual_request` | web | none declared | [routes/web.php](../../../routes/web.php):561 |
| POST | `/cert/manual/upload-file` | `COEController@uploadFileManual` | web | none declared | [routes/web.php](../../../routes/web.php):562 |
| GET | `/cert/manual/pdf/{id}` | `COEController@employedGeneratePDFManual` | web | none declared | [routes/web.php](../../../routes/web.php):563 |
| POST | `/getCertManual` | `COEController@getCertManual` | web | none declared | [routes/web.php](../../../routes/web.php):564 |
| GET | `/verification/manual/{request_id}` | `COEController@verificationManual` | web | none declared | [routes/web.php](../../../routes/web.php):565 |
| GET | `/404` | `COEController@notFound` **MISSING explicit method** | web | none declared | [routes/web.php](../../../routes/web.php):566 |
| GET | `/ipcr` | `IPCRController@index` | web | none declared | [routes/web.php](../../../routes/web.php):568 |
| POST | `/ipcr/add_record` | `IPCRController@add_record` | web | none declared | [routes/web.php](../../../routes/web.php):569 |
| GET | `/ipcr/view/{id}` | `IPCRController@ipcr_form` | web | none declared | [routes/web.php](../../../routes/web.php):570 |
| GET | `/ipcr/edit/{id}` | `IPCRController@edit_ipcr` | web | none declared | [routes/web.php](../../../routes/web.php):571 |
| GET | `/ipcr/division-chief/edit/{id}` | `IPCRController@edit_ipcr_division_chief` | web | none declared | [routes/web.php](../../../routes/web.php):572 |
| POST | `/ipcr/delete` | `IPCRController@delete` | web | none declared | [routes/web.php](../../../routes/web.php):573 |
| POST | `/ipcr/submit` | `IPCRController@submit` | web | none declared | [routes/web.php](../../../routes/web.php):574 |
| GET | `/ipcr/output/{id}` | `IPCRController@output` | web | none declared | [routes/web.php](../../../routes/web.php):576 |
| POST | `/ipcr/save_output` | `IPCRController@save_output` | web | none declared | [routes/web.php](../../../routes/web.php):577 |
| GET | `/ipcr/get_output_details/{output_id}` | `IPCRController@get_output_details` | web | none declared | [routes/web.php](../../../routes/web.php):578 |
| POST | `/ipcr/update_output` | `IPCRController@update_output` | web | none declared | [routes/web.php](../../../routes/web.php):579 |
| POST | `/ipcr/delete_output` | `IPCRController@delete_output` | web | none declared | [routes/web.php](../../../routes/web.php):580 |
| GET | `/ipcr/success_indicators/{id}` | `IPCRController@success_indicators` **MISSING explicit method** | web | none declared | [routes/web.php](../../../routes/web.php):583 |
| POST | `/ipcr/save_success_indicators` | `IPCRController@save_success_indicators` | web | none declared | [routes/web.php](../../../routes/web.php):584 |
| GET | `/ipcr/get_indicator_details/{si_id}` | `IPCRController@get_indicator_details` | web | none declared | [routes/web.php](../../../routes/web.php):585 |
| POST | `/ipcr/update_indicator_details` | `IPCRController@update_indicator_details` | web | none declared | [routes/web.php](../../../routes/web.php):586 |
| POST | `/ipcr/delete_success_indicators` | `IPCRController@delete_success_indicators` | web | none declared | [routes/web.php](../../../routes/web.php):587 |
| POST | `/ipcr/save_actual_accomplishment` | `IPCRController@save_actual_accomplishment` | web | none declared | [routes/web.php](../../../routes/web.php):590 |
| POST | `/ipcr/delete_actual_accomplishment` | `IPCRController@delete_actual_accomplishment` | web | none declared | [routes/web.php](../../../routes/web.php):591 |
| GET | `/ipcr/support_functions/{id}` | `IPCRController@support_functions` | web | none declared | [routes/web.php](../../../routes/web.php):594 |
| GET | `/ipcr-overview` | `IPCRController@staff_overview` | web | none declared | [routes/web.php](../../../routes/web.php):596 |
| GET | `/ipcr/accompishment_details/{data3_id}` | `IPCRController@accompishment_details` **MISSING explicit method** | web | none declared | [routes/web.php](../../../routes/web.php):598 |
| POST | `/ipcr/update_accomplishment` | `IPCRController@update_accomplishment` | web | none declared | [routes/web.php](../../../routes/web.php):599 |
| GET | `/api/user` | `APIController@showAuthenticated` | api (throttle:60,1); auth:api | none declared | [routes/api.php](../../../routes/api.php):15 |
| GET | `/api/users/timelog` | `APIController@timelog` | api (throttle:60,1) | none declared | [routes/api.php](../../../routes/api.php):19 |
| GET | `/api/users/timelog/{myEmail}` | `APIController@timelogByEmail` | api (throttle:60,1) | none declared | [routes/api.php](../../../routes/api.php):21 |
| GET | `/api/holidays` | `APIController@holidays` | api (throttle:60,1) | none declared | [routes/api.php](../../../routes/api.php):23 |
| GET | `/api/dtr-time-log/max-id` | `APIController@getMaxDtrTimeLogId` | api (throttle:60,1); api.token | none declared | [routes/api.php](../../../routes/api.php):27 |
| POST | `/api/dtr-time-log/batch-insert` | `APIController@batchInsertDtrTimeLog` | api (throttle:60,1); api.token | none declared | [routes/api.php](../../../routes/api.php):28 |
| GET | `/api/users` | `APIController@users` | api (throttle:60,1); api.token | none declared | [routes/api.php](../../../routes/api.php):29 |
| GET | `/api/notifications` | `SystemNotificationController@index` | api (throttle:60,1); web,auth | none declared | [routes/api.php](../../../routes/api.php):36 |
| GET | `/api/notifications/count` | `SystemNotificationController@count` | api (throttle:60,1); web,auth | none declared | [routes/api.php](../../../routes/api.php):37 |
| POST | `/api/notifications/read` | `SystemNotificationController@markAsRead` | api (throttle:60,1); web,auth | none declared | [routes/api.php](../../../routes/api.php):38 |
| POST | `/api/notifications/dismiss` | `SystemNotificationController@markAsDismissed` | api (throttle:60,1); web,auth | none declared | [routes/api.php](../../../routes/api.php):39 |
| POST | `/api/notifications/read-all` | `SystemNotificationController@markAllAsRead` | api (throttle:60,1); web,auth | none declared | [routes/api.php](../../../routes/api.php):40 |
| POST | `/api/notifications/dismiss-all` | `SystemNotificationController@dismissAll` | api (throttle:60,1); web,auth | none declared | [routes/api.php](../../../routes/api.php):41 |
| POST | `/api/notifications/create` | `SystemNotificationController@store` | api (throttle:60,1); web,auth | none declared | [routes/api.php](../../../routes/api.php):42 |
| POST | `/api/notifications/external-create` | `SystemNotificationController@externalCreate` | api (throttle:60,1); api.token | none declared | [routes/api.php](../../../routes/api.php):46 |

## Repeated method and path declarations

- `GET /hr/payroll`: `HRPayrollController@index` (routes/web.php:136); `HRPayrollController@index` (routes/web.php:139). Reconcile intended handler before porting.
- `GET /DeductLeaveCredit`: `CronController@DeductLeaveCredit` (routes/web.php:442); `HRStaffController@deduct_tardy_to_credits` (routes/web.php:450). Reconcile intended handler before porting.
- `GET /404`: `COEController@notFound` (routes/web.php:557); `COEController@notFound` (routes/web.php:566). Reconcile intended handler before porting.

## Routes with missing explicit controller methods

- `POST /index/check_last` -> `Auth\LoginController@check_last` (routes/web.php:21). Check trait inheritance before treating as broken.
- `POST /employee/ob/amend` -> `OBController@amend_ob` (routes/web.php:39). Check trait inheritance before treating as broken.
- `GET /dtr/dtr_analyzer` -> `DTRController@dtr_analyzer` (routes/web.php:46). Check trait inheritance before treating as broken.
- `POST /cto/get_info` -> `CTOController@get_info` (routes/web.php:108). Check trait inheritance before treating as broken.
- `GET /hr/payroll/print` -> `HRPayrollController@printReport` (routes/web.php:153). Check trait inheritance before treating as broken.
- `POST /careers/upload_files` -> `RecruitmentExamController@upload_files` (routes/web.php:280). Check trait inheritance before treating as broken.
- `POST /hr/recognition-awardees/show` -> `HRStaffController@nominationAwardeesShow` (routes/web.php:290). Check trait inheritance before treating as broken.
- `POST /announcement/bday` -> `AdminController@bday` (routes/web.php:437). Check trait inheritance before treating as broken.
- `GET /404` -> `COEController@notFound` (routes/web.php:557). Check trait inheritance before treating as broken.
- `GET /404` -> `COEController@notFound` (routes/web.php:566). Check trait inheritance before treating as broken.
- `GET /ipcr/success_indicators/{id}` -> `IPCRController@success_indicators` (routes/web.php:583). Check trait inheritance before treating as broken.
- `GET /ipcr/accompishment_details/{data3_id}` -> `IPCRController@accompishment_details` (routes/web.php:598). Check trait inheritance before treating as broken.
