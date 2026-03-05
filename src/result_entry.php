<!DOCTYPE html>
<html>
<head>
    <title>Enter Internship Result</title>
</head>
<body>

<h2>Internship Result Entry</h2>

<form action="save_result.php" method="POST">

Student ID:
<input type="text" name="student_id" required><br><br>

Undertaking Tasks (10%):
<input type="number" name="task" min="0" max="10" required><br><br>

Health & Safety (10%):
<input type="number" name="safety" min="0" max="10" required><br><br>

Theoretical Knowledge (10%):
<input type="number" name="knowledge" min="0" max="10" required><br><br>

Report Presentation (15%):
<input type="number" name="report" min="0" max="10" required><br><br>

Language & Illustration (10%):
<input type="number" name="language" min="0" max="10" required><br><br>

Lifelong Learning (15%):
<input type="number" name="lifelong" min="0" max="10" required><br><br>

Project Management (15%):
<input type="number" name="project" min="0" max="10" required><br><br>

Time Management (15%):
<input type="number" name="time" min="0" max="10" required><br><br>

Comments:<br>
<textarea name="comment"></textarea><br><br>

<button type="submit">Submit Result</button>

</form>

</body>
</html>