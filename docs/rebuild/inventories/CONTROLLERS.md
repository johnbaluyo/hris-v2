# Legacy controller inventory

Source revision: `c8973c067292967581b0ae5a2fe8493f905fed4c`. Generated from the working tree by [generate-inventories.cjs](../tools/generate-inventories.cjs). This is static source evidence, not a running application or live database inspection. Re-run after source changes.

Controllers: **36**. Methods include stubs and non-routed helpers; a method name is not evidence that a feature is complete.

## APIController

Source: [app/Http/Controllers/APIController.php](../../../app/Http/Controllers/APIController.php). Constructor middleware: none declared.

- `showAuthenticated` (line 13)
- `index` (line 18)
- `timelog` (line 23)
- `timelogByEmail` (line 32)
- `holidays` (line 37)
- `users` (line 43)
- `test` (line 48)
- `getMaxDtrTimeLogId` (line 57)
- `batchInsertDtrTimeLog` (line 79)

## AdminController

Source: [app/Http/Controllers/AdminController.php](../../../app/Http/Controllers/AdminController.php). Constructor middleware: auth.

- `__construct` (line 28)
- `ssi` (line 35)
- `myGateLog` (line 123)
- `user_filter` (line 197)
- `celebrations` (line 315)
- `allGateLog` (line 355)
- `wfhLogsData` (line 441)
- `load_bday_file` (line 617)
- `delete_bday_file` (line 623)
- `upload_gif` (line 634)
- `reset_all_password` (line 657)

## Auth\ConfirmPasswordController

Source: [app/Http/Controllers/Auth/ConfirmPasswordController.php](../../../app/Http/Controllers/Auth/ConfirmPasswordController.php). Constructor middleware: auth.

- `__construct` (line 36)

## Auth\ForgotPasswordController

Source: [app/Http/Controllers/Auth/ForgotPasswordController.php](../../../app/Http/Controllers/Auth/ForgotPasswordController.php). Constructor middleware: none declared.



## Auth\LoginController

Source: [app/Http/Controllers/Auth/LoginController.php](../../../app/Http/Controllers/Auth/LoginController.php). Constructor middleware: guest->except('logout').

- `__construct` (line 44)
- `customLogin` (line 52)
- `index` (line 83)
- `wfh` (line 89)
- `signOut` (line 95)
- `cancel` (line 103)
- `check_id` (line 133)
- `compute` (line 157)
- `ssiLogin` (line 179)

## Auth\RegisterController

Source: [app/Http/Controllers/Auth/RegisterController.php](../../../app/Http/Controllers/Auth/RegisterController.php). Constructor middleware: guest.

- `__construct` (line 39)
- `validator` (line 50)
- `create` (line 65)

## Auth\ResetPasswordController

Source: [app/Http/Controllers/Auth/ResetPasswordController.php](../../../app/Http/Controllers/Auth/ResetPasswordController.php). Constructor middleware: none declared.



## Auth\VerificationController

Source: [app/Http/Controllers/Auth/VerificationController.php](../../../app/Http/Controllers/Auth/VerificationController.php). Constructor middleware: auth; signed->only('verify'); throttle:6,1->only('verify', 'resend').

- `__construct` (line 36)

## COEController

Source: [app/Http/Controllers/COEController.php](../../../app/Http/Controllers/COEController.php). Constructor middleware: none declared.

- `index` (line 17)
- `lists_hr_index` (line 44)
- `manual_index` (line 52)
- `manual_request` (line 63)
- `getEmployee` (line 110)
- `cert_hr_index` (line 125)
- `request` (line 133)
- `getCert` (line 181)
- `getCertReceivedUpdate` (line 197)
- `received` (line 212)
- `getCertAcknowledgedUpdate` (line 235)
- `acknowledged` (line 250)
- `getCreateCert` (line 275)
- `create` (line 290)
- `uploadFile` (line 329)
- `employedGeneratePDF` (line 360)
- `verification` (line 376)
- `verificationManual` (line 390)
- `uploadFileManual` (line 404)
- `employedGeneratePDFManual` (line 435)
- `getCertManual` (line 453)

## CTOController

Source: [app/Http/Controllers/CTOController.php](../../../app/Http/Controllers/CTOController.php). Constructor middleware: auth.

- `__construct` (line 25)
- `index` (line 38)
- `allstaff` (line 90)
- `file_cto` (line 145)
- `cancel` (line 152)
- `overview_index` (line 218)
- `overview_index_oic` (line 300)
- `staff_index` (line 338)
- `get_staffcoc` (line 388)
- `check_coc` (line 479)
- `get_user` (line 498)
- `get_user_coc` (line 521)
- `get_coc_logs` (line 557)
- `save_coc_log` (line 570)
- `delete_coc_log` (line 614)
- `refresh_coc_totals` (line 627)
- `check_dates` (line 644)
- `edit_cto` (line 655)
- `print_cto` (line 702)
- `status_history` (line 751)
- `view_coc` (line 765)
- `certify_cto` (line 803)
- `approvedisapprove` (line 809)
- `update_hours` (line 815)
- `override_cto` (line 860)

## ConcernController

Source: [app/Http/Controllers/ConcernController.php](../../../app/Http/Controllers/ConcernController.php). Constructor middleware: auth.

- `__construct` (line 14)
- `index` (line 24)
- `store` (line 43)
- `uploadFiles` (line 107)
- `removeFile` (line 137)
- `getConcern` (line 176)
- `getConcernUpdate` (line 191)
- `destroy` (line 206)
- `acknowledgeConcernUpdate` (line 217)

## Controller

Source: [app/Http/Controllers/Controller.php](../../../app/Http/Controllers/Controller.php). Constructor middleware: none declared.



## CronController

Source: [app/Http/Controllers/CronController.php](../../../app/Http/Controllers/CronController.php). Constructor middleware: none declared.

- `__construct` (line 25)
- `DeleteTimelogDuplicate` (line 37)
- `GenerateLeaveCredit` (line 42)
- `DeductLeaveCredit` (line 51)
- `AnalyzeTimeLog` (line 60)
- `timelog_to_dtr` (line 72)
- `sendBirthdayGreet` (line 81)
- `ApproveAFL` (line 90)
- `update_filled_positions_status` (line 95)
- `applyPendingCocDeductions` (line 121)

## DTRController

Source: [app/Http/Controllers/DTRController.php](../../../app/Http/Controllers/DTRController.php). Constructor middleware: auth.

- `__construct` (line 28)
- `history` (line 36)
- `time_log` (line 44)
- `get_dtr` (line 50)
- `getSummary` (line 55)
- `AddWorkWeek` (line 112)
- `AddTardiness` (line 126)
- `specialOrderModify` (line 162)
- `workSuspensionModify` (line 196)
- `checkOB` (line 244)
- `modifyDTRLeave` (line 286)
- `checkCTO` (line 337)
- `BestAttendance` (line 394)
- `getWeekDates` (line 674)
- `checkHolidays` (line 691)
- `save_override` (line 760)
- `check_record` (line 768)
- `log_time_ssi` (line 773)
- `log_time_ssi_wfh` (line 777)
- `dtr_remarks_index` (line 782)
- `get_dtr_remarks` (line 795)
- `delete_dtr_remarks` (line 800)
- `save_remarks` (line 805)
- `attendance_record` (line 811)

## DivisionController

Source: [app/Http/Controllers/DivisionController.php](../../../app/Http/Controllers/DivisionController.php). Constructor middleware: none declared.

- `index` (line 15)
- `create` (line 25)
- `store` (line 36)
- `show` (line 47)
- `edit` (line 58)
- `update` (line 70)
- `destroy` (line 81)

## DtrActivityController

Source: [app/Http/Controllers/DtrActivityController.php](../../../app/Http/Controllers/DtrActivityController.php). Constructor middleware: none declared.

- `addDtrActvity` (line 9)

## HRPayrollController

Source: [app/Http/Controllers/HRPayrollController.php](../../../app/Http/Controllers/HRPayrollController.php). Constructor middleware: auth.

- `__construct` (line 29)
- `index` (line 39)
- `store` (line 45)
- `edit` (line 51)
- `staff_index` (line 56)
- `staff_store` (line 77)
- `loan_store` (line 83)
- `loan_delete` (line 139)
- `get_user_details` (line 155)
- `staff_edit` (line 178)
- `staff_delete` (line 183)
- `load_previous_payroll` (line 215)
- `reportPayrollRegular` (line 221)
- `reportPayrollRegularPaySlip` (line 230)
- `exportCswDisbursementVoucher` (line 307)
- `export_csw_payroll` (line 326)

## HRSettingsController

Source: [app/Http/Controllers/HRSettingsController.php](../../../app/Http/Controllers/HRSettingsController.php). Constructor middleware: auth.

- `__construct` (line 23)
- `index` (line 34)
- `sg_index` (line 43)
- `delete_sg` (line 48)
- `get_sg` (line 54)
- `save_sg` (line 59)
- `table1` (line 67)
- `table2` (line 72)
- `table3` (line 77)
- `table4` (line 82)

## HRStaffController

Source: [app/Http/Controllers/HRStaffController.php](../../../app/Http/Controllers/HRStaffController.php). Constructor middleware: auth.

- `__construct` (line 45)
- `index` (line 57)
- `uploads` (line 87)
- `filter` (line 112)
- `dtr_info` (line 145)
- `getDtrYearData` (line 200)
- `check_email` (line 240)
- `check_position` (line 245)
- `check_atms_id` (line 253)
- `syncUsersToSsi` (line 258)
- `store` (line 316)
- `edit` (line 402)
- `archive` (line 407)
- `dtr_year` (line 412)
- `dtrData` (line 437)
- `dtrReport_multi` (line 542)
- `returnSurname` (line 548)
- `monthlyDtrReport` (line 556)
- `datatable_monthlyDtrReport` (line 578)
- `export_monthlyDtrReport` (line 586)
- `weekOfMonth` (line 594)
- `weekOfYear` (line 603)
- `dataSheetUPload` (line 615)
- `showDatasheet` (line 638)
- `nominationAwardees` (line 644)
- `nominationAwardeesSave` (line 672)
- `nominationAwardeesDelete` (line 690)
- `nominationAwardeesVote` (line 696)
- `print_my_dtr` (line 706)
- `save_event` (line 862)
- `delete_event` (line 877)
- `multipleMothlyReport` (line 890)
- `deduct_tardy_to_credits` (line 1042)
- `has_dtr` (line 1152)
- `get_credit_equiv` (line 1559)
- `check_holiday` (line 1594)
- `updateDTR` (line 1635)
- `syncDTR` (line 1651)
- `attendanceReport` (line 1663)
- `setHRDC` (line 1857)
- `setOIC` (line 1870)
- `checkOIC` (line 1885)

## HolidayController

Source: [app/Http/Controllers/HolidayController.php](../../../app/Http/Controllers/HolidayController.php). Constructor middleware: none declared.

- `index` (line 13)
- `saveHoliday` (line 20)
- `editHoliday` (line 53)
- `delete_holiday` (line 63)
- `check_dtr` (line 72)
- `get_tardiness` (line 92)

## HomeController

Source: [app/Http/Controllers/HomeController.php](../../../app/Http/Controllers/HomeController.php). Constructor middleware: auth.

- `__construct` (line 15)
- `index` (line 25)

## IDPController

Source: [app/Http/Controllers/IDPController.php](../../../app/Http/Controllers/IDPController.php). Constructor middleware: auth.

- `__construct` (line 15)
- `index` (line 20)
- `store` (line 99)
- `idp_form` (line 145)
- `get_idp` (line 154)
- `get_idp_with_matrix` (line 160)
- `submit_to_dc` (line 171)
- `sign_dc` (line 183)
- `delete` (line 196)
- `get_trainings` (line 207)

## IPCRController

Source: [app/Http/Controllers/IPCRController.php](../../../app/Http/Controllers/IPCRController.php). Constructor middleware: none declared.

- `__construct` (line 20)
- `index` (line 25)
- `staff_overview` (line 42)
- `view` (line 109)
- `ipcr_form` (line 115)
- `calculateOverallRating` (line 154)
- `getRatingDescription` (line 187)
- `edit_ipcr` (line 202)
- `edit_ipcr_division_chief` (line 212)
- `add_record` (line 222)
- `submit` (line 239)
- `delete` (line 279)
- `output` (line 296)
- `save_output` (line 303)
- `update_output` (line 314)
- `delete_output` (line 324)
- `core_functions` (line 334)
- `save_success_indicators` (line 340)
- `update_indicator_details` (line 351)
- `delete_success_indicators` (line 361)
- `save_actual_accomplishment` (line 371)
- `delete_actual_accomplishment` (line 389)
- `support_functions` (line 399)
- `get_output_details` (line 405)
- `get_indicator_details` (line 418)
- `accomplishment_details` (line 427)
- `update_accomplishment` (line 433)

## LearnAndDevController

Source: [app/Http/Controllers/LearnAndDevController.php](../../../app/Http/Controllers/LearnAndDevController.php). Constructor middleware: auth.

- `__construct` (line 21)
- `index` (line 26)
- `view` (line 59)
- `getPositionMatrix` (line 65)
- `print_staff_matrix` (line 88)
- `staff_matrix_data` (line 95)
- `calculateYearMonthDiff` (line 166)
- `matrix` (line 187)
- `get_matrix` (line 265)
- `save_matrix` (line 270)
- `save_copied_matrix` (line 288)
- `delete` (line 318)
- `importIndex` (line 330)
- `import` (line 335)
- `formatDateRange` (line 355)
- `division_lnd_index` (line 375)
- `create_division_plan` (line 385)
- `load_division_plan` (line 421)
- `save_division_budgets` (line 481)
- `delete_division_plan` (line 532)
- `print_division_plan` (line 567)
- `print_division_plan_range` (line 631)
- `getDatatableData` (line 694)

## LeaveController

Source: [app/Http/Controllers/LeaveController.php](../../../app/Http/Controllers/LeaveController.php). Constructor middleware: auth.

- `__construct` (line 29)
- `index` (line 42)
- `get_staffleave` (line 64)
- `overview_index` (line 109)
- `overview_index_oic` (line 139)
- `submit` (line 182)
- `_delete` (line 198)
- `return_request` (line 204)
- `check_dates` (line 210)
- `check_holiday` (line 216)
- `check_solo` (line 222)
- `leave_details` (line 228)
- `approve` (line 236)
- `get_credits` (line 302)
- `status_history` (line 309)
- `credits` (line 323)
- `get_logs` (line 337)
- `delete_leave_log` (line 342)
- `cancel_leave` (line 368)
- `amend_date` (line 373)
- `leave_report` (line 394)
- `staff_leave_index` (line 447)
- `update_leave_credits` (line 459)
- `calendar` (line 477)
- `feed_calendar` (line 482)
- `leave_template` (line 487)
- `ApproveAFL` (line 516)
- `undo_cancellation` (line 634)
- `override_leave` (line 663)

## OBController

Source: [app/Http/Controllers/OBController.php](../../../app/Http/Controllers/OBController.php). Constructor middleware: auth.

- `__construct` (line 19)
- `index` (line 25)
- `overview_index` (line 81)
- `allstaff` (line 124)
- `allstaff_oic` (line 169)
- `create_ob` (line 201)
- `edit_ob` (line 208)
- `cancel` (line 220)
- `approvedisapprove` (line 232)
- `get_staffob` (line 238)
- `print_ob` (line 305)
- `upload_OB` (line 327)
- `override_ob` (line 354)

## OvertimeController

Source: [app/Http/Controllers/OvertimeController.php](../../../app/Http/Controllers/OvertimeController.php). Constructor middleware: auth.

- `__construct` (line 20)
- `index` (line 34)
- `index_hr` (line 102)
- `allstaff` (line 140)
- `file_ot` (line 197)
- `check_dates` (line 239)
- `edit_ot` (line 245)
- `cancel` (line 256)
- `approvedisapprove` (line 268)
- `print_form` (line 274)

## PersonnelDataManagementController

Source: [app/Http/Controllers/PersonnelDataManagementController.php](../../../app/Http/Controllers/PersonnelDataManagementController.php). Constructor middleware: none declared.

- `__construct` (line 16)
- `index` (line 21)
- `hr_index` (line 41)
- `upload` (line 78)
- `cancel` (line 106)
- `display_year` (line 118)
- `hr_action` (line 130)
- `has_dtr` (line 150)

## ProfileController

Source: [app/Http/Controllers/ProfileController.php](../../../app/Http/Controllers/ProfileController.php). Constructor middleware: auth.

- `__construct` (line 39)
- `index` (line 56)
- `save_account` (line 62)
- `pds_form` (line 67)
- `pds_file` (line 91)
- `personal_info_index` (line 115)
- `save_personal` (line 121)
- `save_fam` (line 131)
- `family_bg_index` (line 136)
- `educ_bg_index` (line 142)
- `save_educ` (line 152)
- `delete_educ` (line 157)
- `civil_service_index` (line 162)
- `save_eligibility` (line 168)
- `save_ld` (line 173)
- `delete_eligibility` (line 219)
- `work_ex_index` (line 224)
- `save_workex` (line 230)
- `save_present` (line 235)
- `delete_workex` (line 240)
- `voluntary_work_index` (line 245)
- `save_vol_work` (line 251)
- `delete_vol_work` (line 256)
- `learning_and_dev_index` (line 261)
- `delete_ld` (line 288)
- `save_other_info` (line 293)
- `save_gov_dec` (line 298)
- `save_char_ref` (line 303)
- `save_gov_id` (line 308)
- `delete_other_info` (line 343)
- `other_info_index` (line 348)
- `govt_declarations` (line 355)
- `character_reference` (line 362)
- `govt_id` (line 368)
- `training_details` (line 374)
- `educ_details` (line 381)
- `elig_details` (line 388)
- `work_details` (line 395)
- `vol_details` (line 402)
- `other_details` (line 409)
- `updateother_details` (line 416)
- `updatevol_details` (line 429)
- `updatework_details` (line 444)
- `updateelig_details` (line 462)
- `updateeduc_details` (line 478)
- `updatetraining_details` (line 495)
- `upload_cert` (line 543)
- `positions_history_index` (line 568)
- `get_workweek` (line 574)

## RecruitmentController

Source: [app/Http/Controllers/RecruitmentController.php](../../../app/Http/Controllers/RecruitmentController.php). Constructor middleware: auth.

- `__construct` (line 37)
- `hr_only` (line 53)
- `index` (line 60)
- `pos_index` (line 68)
- `positions_index` (line 76)
- `delete_positions` (line 82)
- `get_positions` (line 88)
- `save_positions` (line 93)
- `category_index` (line 99)
- `get_category` (line 105)
- `delete_category` (line 110)
- `save_category` (line 116)
- `question_count` (line 121)
- `load_questions` (line 126)
- `delete_question` (line 132)
- `save_question` (line 138)
- `load_choices` (line 148)
- `delete_choice` (line 153)
- `save_choice` (line 158)
- `load_profile` (line 170)
- `save_profile` (line 175)
- `edit_profile` (line 180)
- `delete_profile` (line 185)
- `load_applications` (line 190)
- `load_applications_dropdown` (line 195)
- `save_application` (line 200)
- `requirements` (line 205)
- `upload_requirements` (line 229)
- `delete_uploads` (line 258)
- `upload_eval_docs` (line 272)
- `eval_docs` (line 296)
- `get_application` (line 309)
- `delete_application` (line 314)
- `save_questionnaire` (line 319)
- `load_questionnaire` (line 324)
- `delete_questionnaire` (line 329)
- `toggle` (line 335)
- `submit_application` (line 340)
- `exam_answers` (line 346)
- `load_evaluators` (line 352)
- `get_evaluators` (line 357)
- `delete_evaluator` (line 362)
- `save_evaluator` (line 367)

## RecruitmentExamController

Source: [app/Http/Controllers/RecruitmentExamController.php](../../../app/Http/Controllers/RecruitmentExamController.php). Constructor middleware: auth.

- `__construct` (line 30)
- `applicant_index` (line 43)
- `my_applications` (line 49)
- `exam` (line 55)
- `submit_exam` (line 62)
- `start_timer` (line 67)
- `profile_index` (line 72)
- `save_profile` (line 79)
- `profile_uploads` (line 85)

## SALNController

Source: [app/Http/Controllers/SALNController.php](../../../app/Http/Controllers/SALNController.php). Constructor middleware: auth.

- `__construct` (line 27)
- `index` (line 39)
- `edit_saln` (line 45)
- `add_record` (line 50)
- `saln_form` (line 66)
- `declarant_info` (line 81)
- `save_declarant_info` (line 88)
- `children_info` (line 121)
- `save_child_info` (line 130)
- `delete_child_info` (line 141)
- `assets_real` (line 146)
- `save_real_asset` (line 153)
- `delete_real_asset` (line 170)
- `assets_personal` (line 175)
- `save_personal_asset` (line 182)
- `delete_personal_asset` (line 194)
- `liabilities` (line 199)
- `save_liability` (line 206)
- `delete_liability` (line 218)
- `business_interests` (line 223)
- `save_business_interest` (line 230)
- `delete_business_interest` (line 243)
- `govt_relatives` (line 248)
- `save_govt_relative` (line 255)
- `delete_govt_relative` (line 268)

## SsiController

Source: [app/Http/Controllers/SsiController.php](../../../app/Http/Controllers/SsiController.php). Constructor middleware: none declared.

- `__construct` (line 28)
- `launchDts` (line 36)
- `application` (line 52)
- `user` (line 59)
- `events` (line 67)
- `save_event` (line 79)
- `my_calendar_index` (line 84)
- `psrti_calendar` (line 90)
- `leave_calendar` (line 95)
- `ed_sched` (line 100)
- `get_event` (line 105)
- `event_description` (line 110)
- `delete_event` (line 115)
- `announcements` (line 120)
- `save_announcement` (line 130)
- `delete_announcement` (line 139)
- `my_calendar` (line 144)
- `get_leave` (line 149)
- `get_ob` (line 154)
- `get_cto` (line 159)
- `test_upload` (line 192)
- `handle_test_upload` (line 197)
- `link_index` (line 299)
- `add_link` (line 305)
- `linkUpdate` (line 337)
- `removeLink` (line 352)
- `store_wellness` (line 364)
- `wellnessUpdate` (line 390)
- `delete_wellness` (line 405)
- `applicationFeatures` (line 421)
- `toggleStatus` (line 457)
- `applicationUpdate` (line 489)

## StatusController

Source: [app/Http/Controllers/StatusController.php](../../../app/Http/Controllers/StatusController.php). Constructor middleware: none declared.

- `index` (line 15)
- `create` (line 25)
- `store` (line 36)
- `show` (line 47)
- `edit` (line 58)
- `update` (line 70)
- `destroy` (line 81)

## SystemNotificationController

Source: [app/Http/Controllers/SystemNotificationController.php](../../../app/Http/Controllers/SystemNotificationController.php). Constructor middleware: none declared.

- `index` (line 18)
- `count` (line 49)
- `markAsRead` (line 81)
- `markAsDismissed` (line 127)
- `markAllAsRead` (line 171)
- `dismissAll` (line 199)
- `store` (line 226)
- `externalCreate` (line 319)

## WorkFromHomeAccomplishmentController

Source: [app/Http/Controllers/WorkFromHomeAccomplishmentController.php](../../../app/Http/Controllers/WorkFromHomeAccomplishmentController.php). Constructor middleware: auth.

- `__construct` (line 21)
- `index` (line 26)
- `store` (line 123)
- `update` (line 167)
- `chiefUpdate` (line 212)
- `destroy` (line 267)
- `approveAccomplishment` (line 303)
- `returnAccomplishment` (line 329)
- `consolidate` (line 360)
- `signConsolidated` (line 410)
- `approveConsolidated` (line 454)
- `deleteConsolidated` (line 480)
- `reconsolidate` (line 507)
- `printDivision` (line 553)
- `printAgency` (line 590)
- `getAvailableDatesForStaff` (line 654)
- `getChiefConsolidateDates` (line 681)
- `getWednesdayDates` (line 718)
- `getOnLeaveUserIdsByDivisionAndDate` (line 736)
- `chiefAvailableStaff` (line 764)
- `chiefStoreForStaff` (line 811)
- `isActingChief` (line 861)
- `buildFallbackByUser` (line 894)

