<?php
/**
 * IntelliLearn Teacher Portal feature guide for the chatbot.
 * Built from the actual SUA-IntelliLearn teacher pages and logic
 * (sidebar, class overview, attendance, grade book, at-risk engine,
 * quiz generator, announcements, calendar, settings).
 *
 * Place in: public/teacher/assets/api/teacher_chatbot_guide.php
 * If you rename a menu or button in the UI, update the matching line here.
 */
function teacher_chatbot_system_guide(): string
{
    return <<<'GUIDE'
GENERAL
- The system is "SUA IntelliLearn" (St. Uriel Academy). The teacher portal is called "Teacher's Portal".
- Terms are called TRM 1, TRM 2, TRM 3 (not quarters). Each subject in a section has one separate class (offering) per term, and every term has its own class code. Term dates are set by the administrator.
- The ADMIN (not the teacher) creates classes, assigns teachers to subjects/sections, manages school years and term dates, opens or closes enrollment, and manages accounts. Teachers cannot create classes or student accounts.

NAVIGATION
Left sidebar:
- Main: Dashboard, Classes, Quiz Generator
- Analytics: At-Risk Students, Grade Book, Attendance
- Tools: Announcements, Student Evaluation Results, Calendar, Settings
Top bar: "Search your students..." box (type a name to open a quick student card), the Notifications bell, and the profile menu at top right (Profile Settings, Logout).
On a phone, use the hamburger button to open the sidebar.

DASHBOARD
- Welcome banner with Students, Subjects, and At Risk counts.
- Cards: Total Students Enrolled, Assignments to Grade, Students at Risk of Failing, Average Attendance Rate (this term).
- Panels: At-Risk Students (View All opens the At-Risk page), Recent Activity, and My Classes (grade, section, subject, schedule).

HOW CLASSES ARE ORGANIZED (Sidebar > Classes)
1. The Classes page ("Sections") shows one card per grade/section with subject and student counts. Click a section.
2. The section page lists its subjects as cards (term chip, enrolled/capacity, class code, schedule). Click a subject card ("Open class"). You can search subjects by name, quarter/term, or schedule.
3. The class page has these tabs: Overview, Students, Attendance, Assignments, Quizzes, Enrollment Requests (a badge shows pending requests).
4. On Overview, use the term tabs (TRM 1/2/3) to switch terms. A term that is not set up yet appears disabled with "Not set up yet".

CLASS OVERVIEW
- Stat cards: Enrolled, Materials, Assignments, Class Avg.
- Learning Materials: click "Add Material", enter a Title, then either "Upload a file" (choose type PDF, Slides, Video, or Other; max 25 MB; allowed: pdf, ppt, pptx, doc, docx, xls, xlsx, mp4, mov, jpg, jpeg, png, zip) or "Add a link" (e.g. Google Drive or YouTube URL), then "Save Material". Use the trash icon to remove a material.
- Uploaded text-based PDFs can be used as the source for the AI Quiz Generator.

STUDENTS TAB
- Table of enrolled students for the selected term: LRN, Name, Email, Status.
- To find any student quickly, use the search box in the top bar.

ENROLLMENT REQUESTS (class page > Enrollment Requests tab)
- Teachers decide enrollment; admins do not.
- Students join by typing the class code on their side. The code for each term is shown on the Enrollment Requests tab ("Class codes") and as a badge on the subject card. Share the right term's code with students.
- Each join creates a pending request. In the Enrollment Requests tab: "Approve" enrolls the student, "Deny" rejects it. If the class is at capacity, Approve is replaced by a "Class full" chip.
- "Recently decided" lists past decisions; a denied request has a "Reopen" button to put it back to pending.
- If the admin has closed enrollment, students see "Enrollment is currently closed" and cannot send requests.

ATTENDANCE
Two places:
A) Taking attendance (class page > Attendance tab):
1. Pick the date using the date picker or the arrows ("Today" jumps to today). Future dates cannot be selected.
2. Each student has Present / Absent / Late / Excused buttons and an optional Remarks box. Rows default to Present, so change only the exceptions. "Mark all" can set everyone to Present or Absent.
3. Click "Save Attendance". Saving the same date again updates the existing records.
4. The chips above the list show that day's totals (Present, Absent, Late, Excused, Unmarked).
B) Attendance overview (sidebar > Attendance): date arrows, a term filter (All Terms, TRM 1, TRM 2, TRM 3), My Classes with a "Manage" button for each class (search box available), an Attendance Trend chart, and a "Students Needing Attention" table (Student, Class, Attendance, Absent, Status) for students below 75% attendance in the selected term.
- This page counts attendance rate as (Present + Late) divided by all records. The At-Risk page uses a different weighting (see AT-RISK STUDENTS).
- Attendance records are grouped by the term in which the date falls.

ASSIGNMENTS (class page > Assignments tab)
- Create: click "New Assignment". Fields: Title, Instructions (optional), Due date (optional), Points, Attempts allowed, Grading category (Written Works, Activity (Performance Task), or Exam), Attachment (optional). Click "Post Assignment".
- The list shows category, points, due date, attempts, and graded/submitted counts. Each row has "View Submissions", a pen icon (edit), and a trash icon (delete).
- Grade: click "View Submissions". The table has Student, Status, Attempts, Submission (file/text), Score (out of the assignment's points), and Feedback. Enter scores and optional feedback, then click "Save Grades". Blank scores are left unchanged. A score above the maximum is capped at the maximum.
- Edit (pen icon): change title, instructions, deadline (quick chips +1 day, +3 days, +1 week, No deadline), attempts allowed ("Add 1 attempt"), points, and grading category. Changes apply to students right away.
- Delete (trash icon) permanently removes the assignment and all its submissions after a confirmation. It cannot be undone.
- Assignments without a due date do not appear on the Calendar and are not counted as missing.

QUIZZES (class page > Quizzes tab)
- The list shows status, number of questions, total points, attempts submitted, and average. "New Quiz" opens the AI Quiz Generator for this class. "View Scores" opens per-student results.
- Scores page: quiz attempts are auto-checked. You can change a student's score directly and click "Save Scores"; changes are saved as final. The "Answers" button opens a question-by-question review where you can mark answers correct/incorrect, adjust points (useful for identification or short answers), and click "Save Corrections".

AI QUIZ GENERATOR (sidebar > Quiz Generator)
1. Under "Generate a New Quiz", choose the Subject / Class.
2. Set Number of Items (1 to 50) and Difficulty: Easy, Average, Difficult, or Custom mix (items per difficulty must add up to the Number of Items).
3. Choose Question Type: Mix of types, Multiple Choice, True / False, or Identification.
4. Choose Quiz Source: "From class PDF modules" (pick up to 3 text-based PDFs uploaded in that class's Learning Materials; 25 MB each) or "From a topic I describe" (up to 2000 characters). Optionally enter a Quiz Title (AI suggests one if blank).
5. Click the Generate button. The AI creates a DRAFT only; nothing is saved yet.
6. In "Review & Edit", check and edit questions, or use "Add Question Manually". Set Description, Time Limit (minutes, optional), Max Attempts (default 1), "Shuffle questions for students", Available From, and Available Until (deadline, optional).
7. Click "Save as Draft" (not visible to students) or "Publish Quiz". A quiz cannot be published with an "Available Until" time that is already past.
- "Recent Quizzes" on the right lists your quizzes with draft or published status.
- AI-generated questions can contain mistakes, so teachers should always review before publishing.

GRADE BOOK (sidebar > Grade Book)
- Choose a class from "Your Classes" (on mobile use "Switch class"); each class has a term picker.
- The Grade Book does NOT compute percentages or final grades. It shows completion status only.
- Table columns: Student, Written Works, Performance Tasks, Exam, Details. Each shows Complete, Incomplete, or N/A.
- Written Works = all quizzes plus assignments with category Written Work. Performance Tasks = assignments with category Activity. Exam = assignments with category Exam. Draft assignments and draft quizzes are excluded.
- "Complete" means the student submitted every published item in that category. "Incomplete" means at least one item is not submitted. "N/A" means no items exist in that category yet.
- "View Scores" opens a student's page listing each quiz/activity with its Score and whether it is Submitted or Not Submitted.
- "Export Gradebook" downloads the class's gradebook as an Excel (.xlsx) file.

AT-RISK STUDENTS (sidebar > At-Risk Students)
- Page features: English/Tagalog toggle (EN / TL), a "Search student name" box, filters (All, High, Medium, Low, Insufficient Data), class tabs, and a table with Student, Attendance, Assignments, Quizzes, Risk, and AI Insights.
- How risk is calculated: performance = weighted average of Attendance (30%), Assignment scores (40%), and Quiz scores (30%). Risk score = 100 minus performance. High risk is 60 or more, Medium is 35 to below 60, Low is below 35.
- Attendance credit: Present = full, Late = half, Excused = full, Absent = zero.
- Assignments only count once they are past due. A past-due assignment with no submission counts as 0 (missing). Work that is submitted but not yet graded is left out, so students are not penalized for ungraded work. The best graded attempt is used.
- Quizzes count when published and past their Available Until time (or with no deadline). A due quiz with no finished attempt counts as 0. The best attempt is used.
- A category needs at least 3 items (attendance records, due assignments, or due quizzes) before it is counted. Until then it shows "building data (n/3)" and its weight is shared among the other categories. If no category has enough data, the student shows "Insufficient Data".
- "AI Insights" button on a student's row opens a panel with a snapshot, key observations, why the student is at risk, how it happened, and recommended actions. It can be regenerated. This is AI-written guidance and should be treated as a suggestion.
- The Dashboard "At Risk" numbers come from this same engine.

CALENDAR (sidebar > Calendar)
- Shows assignment and quiz deadlines by month. Use the arrows to change month and "Today" to return.
- Cards: Deadlines This Month, Waiting to Grade, Due Today, Fully Graded, No Due Date.
- Colors: Activity, Exam, Quiz, All graded. Click a day to see that day's agenda.
- "Waiting to Be Graded" lists work with submissions that have not been scored yet, oldest deadline first.
- "No Due Date Set" lists items that cannot be placed on the calendar. Set a deadline by editing the assignment (or quiz).

ANNOUNCEMENTS (sidebar > Announcements)
1. Click the new announcement button at the top of the page.
2. Enter a Title, choose the Class (one of your classes or "All my classes"), and write the Message.
3. Optionally tick "Pin to top of announcements".
4. Click "Publish" to post now, or "Save as Draft".
- Teacher announcements go to students only. Tabs: "My Announcements" and "From Administration" (read-only messages from the admin).
- On your announcements you can edit, publish a draft, pin/unpin, or delete.

STUDENT EVALUATION RESULTS (sidebar)
- Shows the teacher's own results from student evaluation rounds run by the administrator. Teachers see only their own results, and students' written comments are not shown to teachers.

SETTINGS / PROFILE (sidebar > Settings, or profile menu > Profile Settings)
- Account Information: username, first name, last name, email, department, specialization.
- Change Password: Current Password, New Password (at least 8 characters, with at least one uppercase letter and one special character), and Confirm New Password.
- Logout is in the profile menu at the top right.

NOTIFICATIONS
- The bell icon in the top bar shows notifications. Open it to read them.

WHAT TEACHERS CANNOT DO IN THE SYSTEM
- Create classes or change class assignments, create or delete student accounts, or open/close enrollment (admin only).
- Post announcements to teachers or the whole school (teacher posts go to students only).
- Get computed final grades or percentage grades from the Grade Book (it only shows completion status).
- The assistant chat is read-only: it can explain and report, but it cannot change records.
GUIDE;
}