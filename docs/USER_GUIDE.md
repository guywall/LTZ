# Less Than Zero Operational Intelligence User Guide

This guide explains how to use the Less Than Zero Operational Intelligence web app, what each area is for, and how the RAG status is calculated.

## 1. What The App Does

The app collects dated operational submissions from each department and turns them into management dashboards.

The main areas are:

- Barbers: takings, RTB, days worked, rebooking, utilisation, and notes.
- Training: learner attendance, progress, EPA readiness, safeguarding flags, and risk notes.
- Social: posts, reels, reach, engagement, leads, follow-ups, conversion, and notes.
- HR: recruitment requirements, pipeline, interviews, offers, gaps, and notes.
- Leadership: overall RAG view for shareholders.
- 5x5: strategic run-rate against growth targets.
- Risks and Actions: governance follow-up lists.

Users only see the areas they are allowed to access. Shareholders and admins can see the full app.

## 2. Logging In

Go to the app login page and enter your assigned email address and password.

The email address is only used as a username. The app does not send password reset emails, because some accounts may use dummy email addresses.

After logging in, use the navigation bar at the top of the screen to move between areas.

## 3. Changing Your Password

Click your name in the top-right corner to open Profile.

Enter:

- Current password: your existing password.
- New password: the new password you want to use.
- Confirm new password: repeat the new password.

Passwords must be at least 10 characters.

Admins and shareholders can also reset another user's password from Admin > Users.

## 4. Dashboards And Reporting Periods

Dashboards show data for the selected reporting period.

Available presets:

- This week
- Last week
- Next week
- Month to date
- Last month
- Year to date
- Custom range

Use From and To if you need a specific date range.

Important: reporting filters only change what you are viewing. They do not change the date used when you submit new data. New submissions are always saved with today's date.

## 5. RAG Status

RAG means:

- Green: performance is at or above target.
- Amber: performance is below target but above the warning threshold.
- Red: performance is below the warning threshold or requires urgent attention.

RAG values are calculated automatically from the fields entered by users.

Typical thresholds:

- Barber RTB: green at 500 or above, amber at 400 or above, red below 400.
- Barber days worked: green at 5 or above, amber at 4 or above, red below 4.
- Training attendance: green at 90% or above, amber at 80% or above, red below 80%.
- Safeguarding flags: green when 0, amber when 1, red when 2 or more.
- Social posts: green at 5 or above per brand, amber at 3 or above, red below 3.
- Social reels: green at 3 or above per brand, amber at 2 or above, red below 2.
- Social follow-up rate: green at 90% or above, amber at 75% or above, red below 75%.
- Recruitment pipeline: green when pipeline meets or exceeds required count, amber when partly covered, red when no meaningful coverage.

Admins can edit KPI targets from Admin > Targets.

## 6. Barber Submissions

Go to Barbers.

Use this area to record barber performance. Each submission is saved against today's date.

Fields:

- Site: the shop/site the submission relates to.
- Barber: the barber the submission relates to.
- RTB cash: revenue to business taken in cash.
- RTB card: revenue to business taken by card.
- Total sales: total sales for the barber in the period being entered.
- Days worked: number of days worked. Decimals are allowed, such as 4.5.
- Rebooking %: percentage of clients rebooked. Enter as a normal percentage, for example 80 for 80%.
- Utilisation %: how much of available working time/chair time was used. Enter as a normal percentage.
- Notes: any context, explanation, or operational comment.

How it is used:

- RTB is calculated from RTB cash plus RTB card.
- RTB and days worked drive the barber RAG.
- Rebooking and utilisation appear in the table to highlight quality and efficiency.
- Dashboard cards link through to the relevant barber detail screen.

If an entry is wrong, users with barber write permission can delete it using the Delete button.

## 7. Training Submissions

Go to Training.

Use this area to record learner progress and training health. Each submission is saved against today's date.

Fields:

- Learner: select the learner record. Learners must be created first in the Learners section.
- Attendance %: attendance level for the submission period. Enter as a normal percentage.
- Progress %: estimated progress through the learner pathway. Enter as a normal percentage.
- EPA readiness: whether the learner is On Track, At Risk, or Not Ready.
- Safeguarding flags: number of unresolved safeguarding flags.
- Risk notes: notes about attendance, progress, safeguarding, or intervention needed.

How it is used:

- Attendance and safeguarding flags drive the training RAG.
- Learner records accumulate submissions over time.
- Attendance and progress are averaged over the learner's logs.
- Learner records include charts for attendance and progress over time.

If an entry is wrong, users with training write permission can delete it.

## 8. Learners

Go to Learners.

Learners are managed as their own records, not as free text.

Fields when adding a learner:

- Name: learner's name.
- Status: Active, Paused, Completed, or Withdrawn.
- Notes: optional background notes.

Statuses:

- Active: appears in the Training submission dropdown.
- Paused: kept on record but not treated as active.
- Completed: kept for historic reporting.
- Withdrawn: kept for historic reporting.

Actions:

- View: opens the learner record.
- Status: changes the learner's current status.
- Remove: deletes the learner, if permitted.

Learner record:

- Shows total logs.
- Shows average attendance.
- Shows average progress.
- Shows total safeguarding flags.
- Shows latest EPA readiness.
- Shows attendance and progress charts.
- Shows all dated logs linked to that learner.

## 9. Social Submissions

Go to Social.

Use this area to record brand and social media performance. Each submission is saved against today's date.

Fields:

- Brand: the brand the figures relate to.
- Posts: number of feed/static posts.
- Reels: number of reels/video posts.
- Reach: total reach.
- Engagement: total engagement count.
- Leads: number of leads generated.
- Follow-ups: number of leads followed up.
- Conversion %: percentage conversion. Enter as a normal percentage.
- Notes: context about campaigns, content, issues, or opportunities.

How it is used:

- Posts, reels, and follow-up rate drive the social RAG.
- Leads appear in the executive dashboard.
- Follow-up rate is calculated from follow-ups compared with leads.

If an entry is wrong, users with social write permission can delete it.

## 10. HR Submissions

Go to HR.

Use this area to record recruitment pipeline and hiring gaps. Each submission is saved against today's date.

Fields:

- Role: the recruitment role being tracked.
- Required: how many people are required for that role.
- Active pipeline: number of live candidates currently in the pipeline.
- Interviews: number of interviews arranged or completed.
- Offers: number of offers made.
- Notes: recruitment context, blockers, or next steps.

How it is used:

- Pipeline compared with required count drives the HR RAG.
- Gap is calculated as Required minus Active pipeline.
- Senior Barber Pipeline appears in the executive and 5x5 dashboards.

If an entry is wrong, users with HR write permission can delete it.

## 11. Risk Register

Go to Risks.

Use this area to record operational risks that need leadership visibility.

Fields:

- Owner: person responsible for managing the risk.
- Priority: High, Medium, or Low.
- Status: Open, In Progress, or Closed.
- Due date: target date for review or resolution.
- Trigger: short description of what caused or revealed the risk.
- Risk: full explanation of the risk.
- Notes: optional extra context.

How it is used:

- Open risks appear on the executive dashboard.
- Risk/action scoring contributes to leadership RAG.
- Closed or low priority items score better than high/open items.

Users with leadership write permission can delete incorrect risk entries.

## 12. Action Tracker

Go to Actions.

Use this area to record follow-up tasks and commitments.

Fields:

- Owner: person responsible for the action.
- Priority: High, Medium, or Low.
- Status: Open, In Progress, or Closed.
- Due date: target completion date.
- Linked area: department or topic the action relates to.
- Action: what needs to be done.
- Notes: optional context.

How it is used:

- Open actions appear on the executive dashboard.
- Action status and priority contribute to leadership RAG.

Users with leadership write permission can delete incorrect action entries.

## 13. Leadership Dashboard

Go to Leadership.

This is mainly for shareholders and leadership users.

It shows each leader with:

- Area of responsibility.
- Revenue score.
- Brand score.
- Training score.
- Recruitment score.
- Risk/action score.
- Overall RAG.

The leadership RAG is calculated from the average score across the leader's relevant operational areas and their risks/actions.

## 14. 5x5 Dashboard

Go to 5x5.

This compares current performance with strategic required run-rate.

It shows:

- RTB
- Occupied chairs
- Active learners
- Social leads
- Senior barber pipeline

Each KPI shows current value, required value, variance, and status.

## 15. Admin Area

Go to Admin.

Only admin/shareholder users should see this area.

Admin > Users:

- Create users.
- Assign roles.
- Reset passwords.

Admin > Lookups:

- Add lookup values such as sites, barbers, brands, recruitment roles, and leaders.

Admin > Targets:

- Edit KPI target values.
- Edit amber thresholds.
- Review notes and units.

Be careful when changing targets, because this changes future dashboard RAG results immediately.

## 16. Good Data Entry Practice

Use one submission per real reporting update.

Enter percentages as normal user-facing percentages:

- Enter 80 for 80%.
- Enter 75.5 for 75.5%.

Use notes when:

- A figure looks unusual.
- A target was missed.
- A team member needs support.
- A risk or blocker needs leadership attention.

If a mistake is made, delete the incorrect entry and re-enter it correctly, assuming your role has delete permission.

## 17. Access Levels

General users see only their functional area.

Examples:

- Barber users see the barber dashboard and barber submissions.
- Training users see training, learners, and learner records.
- Social users see social dashboard and social submissions.
- HR users see HR dashboard and HR submissions.
- Shareholders see all dashboards, submissions, risks, actions, strategy, and admin areas.

The app enforces permissions even if someone tries to visit a page directly by URL.

## 18. Common Questions

Why can I not see a menu item?

Your account probably does not have permission for that area.

Why does a new submission use today's date?

Submissions are treated as dated entries. Reporting periods are selected later using dashboard filters.

Why is a learner missing from the Training dropdown?

Only Active learners appear in the Training submission dropdown. Check the learner status in Learners.

Why is a RAG status red?

The submitted value is below the target threshold, or a risk/safeguarding condition requires attention.

Can passwords be reset by email?

No. Email addresses may be dummy usernames, so password changes are handled inside the app by the user or by an admin/shareholder reset.
