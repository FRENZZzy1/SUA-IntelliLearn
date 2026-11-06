<?php
/**
 * IntelliLearn Admin Portal feature guide for the admin chatbot.
 * Built from the actual SUA-IntelliLearn admin pages and logic
 * (sidebar, dashboard, user management, classes & subjects, settings,
 * announcements, system analytics, access control).
 *
 * Place in: public/admin/assests/api/admin_chatbot_guide.php
 * If you rename a menu or button in the UI, update the matching line here.
 */
function admin_chatbot_system_guide(): string
{
    return <<<'GUIDE'
GENERAL
- The system is "SUA IntelliLearn" (St. Uriel Academy). The admin side is the "Admin Portal". Grade levels are 7 to 12. Senior high students have a strand (GAS, ABM, HUMSS, TVL). Strand is a STUDENT attribute, not a section attribute.
- Terms are called TRM 1, TRM 2, TRM 3 (not quarters). Each subject in a section has one separate class (class offering) per term, and every class has its own class code.
- Roles: Admin, Teacher, Student. The admin manages accounts, classes, school years, terms, enrollment on/off, announcements and the teacher evaluation survey.
- Admins do NOT approve or deny enrollment. The class's teacher does (see ENROLLMENT).

NAVIGATION
Left sidebar (collapsible; on a phone use the hamburger button). Only modules the admin has permission for are shown.
- Main: Dashboard, User Management, Classes & Subjects
- Management: Announcements
- Reports: System Analytics, Settings
Top bar (every page): the global search box (placeholder "Search users, courses, subjects..."; press / or Ctrl+K to focus it), the Notifications bell, the Help button (Help and about SUA IntelliLearn), and the account menu with "Profile Settings" and "Logout".
The AI chat assistant is the floating round chat button at the bottom-right of the Dashboard.
ALWAYS use the exact names in this guide (they are the on-screen labels). Never rename a page, tab or button, and never invent ones such as "Student Management", "Course Management", "Enrollment page" or "Reports page".

TASK-TO-PATH MAP (exact click paths)
- Add a student / teacher / admin account: User Management > pick the Students, Teachers or Admins tab > "Add User" (popup "Add New User", choose "Select Role").
- Add many students at once: User Management > Students tab > "Batch Add via Excel".
- Edit, view or deactivate an account: User Management > the person's row > View Profile (eye), Edit (pencil) or Deactivate (ban) icon.
- Reset someone's password: User Management > row > Edit > "New password".
- Create a subject: Classes & Subjects > "New Subject". Create a section: Classes & Subjects > "New Section". Create a class: Classes & Subjects > "New Class" (or Dashboard > Quick Actions > "Create Class").
- Assign or change a section adviser: Classes & Subjects > "Sections" tab > the section's Update button.
- Change a class's teacher, schedule, capacity or status: Classes & Subjects > "Classes" tab > the class row > "Update class" (the popup is titled "Update Course").
- Find a class code: Classes & Subjects > "Classes" tab > "Class Code" column (click it to copy).
- See or unenroll a class's students: Classes & Subjects > "Classes" tab > the class row > "View students".
- Set term dates: Classes & Subjects > the "Set Term Interval" button next to the tabs.
- Export classes of a teacher, section or student: Classes & Subjects > "Search & Export" tab.
- Add a school year / set the current one / archive it: Settings > "School Year & Terms" tab.
- Open or close enrollment, change default class capacity: Settings > "Enrollment Rules" tab.
- Change the school name: Settings > "General" tab.
- Send or close the teacher evaluation survey: Settings > "Teacher Evaluation" tab (Full Access admins only).
- See teacher evaluation results or the AI summary: System Analytics.
- Post, pin or delete an announcement: Announcements > "Create Announcement".
- Download an Excel report: Dashboard > Quick Actions > "Generate Report".
- Download a database backup: Dashboard > Quick Actions > "Backup Data".
- Change your own password: account menu (top right) > "Profile Settings".
- See pending enrollment requests: the Dashboard does NOT list them. Ask this assistant (it reports them by class and teacher). The teacher decides them in the class's Class Overview > "Enrollment Requests".

ADMIN ACCESS LEVELS (set per admin in User Management)
- Full: every module, read and write. Only Full Access admins can send or close a teacher evaluation survey. A Full-access admin who is not the Principal gets a limited number of days (1 to 365). When it runs out the account is downgraded to Read Only automatically. The Principal keeps Full access permanently.
- Limited: each module is set to Read or Read & Write.
- Read Only: selected modules can be viewed but nothing can be changed. Write buttons are hidden.
- Positions available when creating an admin: Principal, Registrar, IT Administrator.
- Modules: Dashboard, User Management, Classes & Subjects, Announcements, System Analytics, Settings.
- If a button is missing, the likely cause is that the admin only has Read permission for that module.

DASHBOARD
- Top stat cards: Total Students (link "Manage"), Total Teachers (link "Manage"), Active Courses (link "View all"), Announcements (link "View all").
- Quick Actions: Add User Account, Create Class, Post Announcement, Generate Report, Backup Data.
- Panels: Announcements, Quick Actions, System Usage Statistics (active users today/this week, quizzes this week; an approximation based on account activity), Recent Users (link "Manage users"), Courses & Subjects (link "View All Courses"), Course Enrollment Progress (enrolled vs capacity per class). There is no pending-enrollment panel.
- Generate Report: pick a report type (Enrollment, Attendance or Grades), a school year and optionally a term (All terms, TRM 1, TRM 2, TRM 3). It downloads an Excel (.xlsx) file built from live data.
- Backup Data: downloads a full SQL dump of the database as a .sql file. Do this before big changes such as archiving a school year or bulk imports.

USER MANAGEMENT (sidebar > User Management)
- Summary cards at the top: Total Users, Students, Teachers, Admins. Role tabs, in this order: Admins, Teachers, Students. Under the tabs: a search box ("Search <role> by name or email..."), a status filter (All Status, Active, Suspended), "All Departments" (teachers tab only), "All Year Levels" and "All Genders" (students tab only), a sort menu (Newest First, Oldest First, Name A-Z, Name Z-A), pagination and "Clear Filters".
- Header buttons (write access only): "Batch Add via Excel", "Export Student Accounts", "Add User".
- Add User (popup "Add New User"): under "Select Role" choose Student, Teacher or Admin, then fill the fields.
  - Student: First/Last/Middle name, LRN (12 digits), Email, Birthdate, Year Level (Grade 7-12), Strand (None, GAS, ABM, HUMSS, TVL), Gender, Address, Guardian Name, Guardian Contact, password.
  - Teacher: name, Email, Contact Number, Department (Senior High only, High School only, or Highschool/Senior High), Employment Status (Full-time or Part-time), Specialization, password.
  - Admin: name, Email, Position (Principal, Registrar, IT Administrator), Access Level (Full, Limited, Read Only), Full Access Duration in days when applicable, and module permissions for Limited admins.
- Batch Add via Excel (students): upload an .xlsx file. Required columns: firstname, lastname, lrn, birthdate, gender, year_level (7 to 12). A template can be downloaded in the modal. Rows with duplicate LRNs are skipped and reported.
- Row icons: eye = "View Profile" (popup "User Profile", includes an activity History), pencil = "Edit" (popup "Edit User"), ban icon = "Deactivate" (suspends the account; not available on your own account).
- Edit User lets the admin change profile fields, Status, and set a new password (leave blank to keep the current one).
- Teachers cannot create student accounts or classes. Only admins can.
- Password rule for accounts: at least 8 characters, with at least one uppercase letter and one special character.

CLASSES & SUBJECTS (sidebar > Classes & Subjects)
Header buttons: "New Subject", "New Section", "New Class". Page tabs, in this order: "Classes", "Subjects", "Sections", "Search & Export" (each shows a count). The "Set Term Interval" button sits to the right of the tabs; it is a button, not a tab. Filters on the Classes tab: All Grade Levels, All Terms, All School Years, status (All, Active, Inactive, Archived) and a search box.
Building blocks and the order to create them:
1. School year (Settings). 2. Subjects (New Subject: name and description). 3. Sections (New Section: Section Name, Grade Level, optional Adviser). 4. Term intervals (Set Term Interval). 5. Classes (New Class).
Creating a class (New Class, popup titled "Add New Class"): choose Subject, Section, Teacher, School Year, Capacity (pre-filled from the default class capacity setting), Status (Active or Inactive), Schedule Days (weekday buttons), Start Time and End Time.
- The term is NOT picked manually. It is worked out automatically from today's date using the term intervals. If no term is active today, class creation fails and the admin must set the term intervals first.
- The same subject cannot be offered twice to the same section in the same term and school year.
- A class code is generated for every class. It is shown in the "Class Code" column of the Classes tab (click it to copy). Students type the code on their side to request to join.
- The Classes tab shows enrolled/capacity per class, teacher, schedule, term, status and a "Class Code" column. Row buttons: "View students", "Update class" (opens the popup titled "Update Course"), and "Restore class" for archived classes.
- Set Term Interval: enter a Start Date and End Date for TRM 1, TRM 2, and TRM 3, then Save Term Intervals. Term dates are NOT tied to a school year, so set them again for each new school year. When the term changes, each class is copied into the next term together with its enrolled students.
- Sections tab: update a section, assign or change its adviser, archive or restore a section. Archiving a section hides every class in it from teachers and students.
- Subjects tab: update a subject, archive or restore. An archived subject cannot be picked for new classes.
- Search & Export: search by teacher, section, or student and export their classes to Excel (.xlsx). Course student lists can also be exported.
- Classes are no longer archived by hand. They are archived automatically when their school year ends. Restore brings them back.

ENROLLMENT
- Students join a class by typing its class code. This creates a PENDING enrollment request.
- The class's TEACHER approves or denies the request in the class's Class Overview > "Enrollment Requests". Admins do not decide enrollment. A denied request can be reopened by the teacher. If the class is at capacity the teacher cannot approve until a seat frees up.
- Admins control enrollment globally: Settings > Enrollment Rules > "Enrollment open". When it is off, students cannot send requests at all ("Enrollment is currently closed").
- Enrollment statuses: pending, enrolled, denied, dropped, completed.
- The Dashboard does not list pending requests; this assistant reports them (by class and teacher). Admins can unenroll students from a class's "View students" popup (Classes & Subjects > Classes tab > View students). Unenrolling marks the student as "dropped" in ALL term offerings of that class (same subject, section and school year), not just the term that was opened. Nothing is deleted, so grades are kept, and re-enrolling the student re-activates the dropped records.
- Typical problems: "student can't join" = enrollment closed, wrong term's class code, class full, class inactive or archived, or the request is still pending with the teacher.

SETTINGS (sidebar > Settings). Tabs:
A) School Year & Terms
- Lists school years. Only ONE school year is current at a time. New sections and enrollments are created under the current year.
- Add School Year: Label (max 9 characters, e.g. "SY 2027"), Start Date, End Date, optional "Make this the current school year", and "Copy sections & classes from" another year (recreates each section and class with its teacher, schedule and capacity as a TRM 1 class; students are NOT carried over).
- Edit corrects a label or dates. Set current changes which year is current.
- Archiving: once a year's end date passes it is archived automatically. Its classes and sections are hidden from teachers and students' class lists, nothing is deleted, and students keep their grade history. Restore brings it back and stops automatic archiving until the admin archives it again by hand with Archive.
- Warning: setting an end date that is already in the past will cause automatic archiving.
- About Terms: a new school year starts without students. Recreate the structure with "Copy sections & classes from", then set the term intervals again.
B) Enrollment Rules
- Default Class Capacity (1 to 500; the pre-filled capacity for new classes) and the "Enrollment open" switch.
C) General
- School Information: the School Name, which is shown across the portal (sidebar, login page, exports).
D) Teacher Evaluation (survey)
- Only Full Access admins can send or close a survey. Others can view status.
- Send a survey: optional Survey title, Term to evaluate (TRM 1, TRM 2, TRM 3), optional Deadline (cannot be in the past), then "Send survey to students". Students then see it on their dashboard and under Teacher Evaluation, and rate each teacher they are enrolled with for that term.
- While a survey is live the tab shows progress ("x of y class evaluations submitted") and a "Close survey" button. Only one survey can be live at a time: a new one cannot be sent until the open one is closed. Past surveys are listed under Recent surveys.

ANNOUNCEMENTS (sidebar > Announcements)
- "Create Announcement": Title, Audience (All, Teachers, or Students), Message, optional Pin to top. Buttons: Save as Draft or Publish.
- Admin announcements appear to the chosen audience. Drafts are not visible to anyone else. You can edit, publish a draft, pin/unpin, or delete.
- The page also shows announcements posted by teachers (read-only for the admin). Teacher announcements go to students only.

SYSTEM ANALYTICS (sidebar > System Analytics)
- Shows Teacher Evaluation results for a chosen survey round: overall rating, average by category, how students rated (1 to 5 distribution), results by teacher, response rate by section, and per-teacher detail.
- "AI Survey Summary" / "Generate summary": an on-demand AI summary. It needs a minimum number of responses (3) and is not stored. Requires write access to Analytics.
- Rating bands: 4.5 and above Outstanding, 3.5 to 4.49 Very Satisfactory, 2.5 to 3.49 Satisfactory, 1.5 to 2.49 Unsatisfactory, below 1.5 Poor.
- If the page shows "Setup required", the teacher evaluation database tables have not been created yet (run the teacher evaluation migration).
- Teachers only see their own results, and students' written comments are never shown to teachers.

PROFILE (account menu at top right > "Profile Settings")
- Account Information (email, position) and Change Password (current password, new password with at least 8 characters, one uppercase letter and one special character, confirm).

WHAT TEACHERS CAN DO (so the admin knows who handles what)
- Teachers: take attendance, post assignments, build AI quizzes, grade, approve/deny enrollment requests, manage learning materials, see an At-Risk Students report, and post announcements to their students. Teachers cannot create classes or accounts, change class assignments, or open/close enrollment.

WHAT STUDENTS CAN DO
- Join classes with a class code, view materials, submit assignments, take quizzes, see grades and attendance, read announcements, and complete the teacher evaluation survey when one is open.

WHAT THE ASSISTANT CANNOT DO
- It is read-only. It can explain and report but cannot create, edit, delete, approve or send anything. It points the admin to the page and steps instead.
GUIDE;
}
