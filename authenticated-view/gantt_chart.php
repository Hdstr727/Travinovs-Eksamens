<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gantt Chart</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="gantt-container">
        <h2>Gantt Chart</h2>

        <!-- Task Input -->
        <div class="task-form">
            <input type="text" id="task-name" placeholder="Task Name">
            <label>Start Date:</label>
            <input type="date" id="start-date">
            <label>End Date:</label>
            <input type="date" id="end-date">
            <button onclick="addTask()">Add Task</button>
        </div>

        <!-- Gantt Chart Display -->
        <div class="gantt-chart">
            <div class="gantt-header">
                <span>Task</span>
                <span>Start Date</span>
                <span>End Date</span>
                <span>Duration</span>
                <span>Progress</span>
                <span>Action</span>
            </div>
            <div id="task-list"></div>
        </div>
    </div>

    <script>
        function addTask() {
            let taskName = document.getElementById("task-name").value.trim();
            let startDate = document.getElementById("start-date").value;
            let endDate = document.getElementById("end-date").value;
            let taskList = document.getElementById("task-list");

            if (taskName === "" || startDate === "" || endDate === "") {
                alert("Please fill in all fields.");
                return;
            }

            let start = new Date(startDate);
            let end = new Date(endDate);
            let duration = (end - start) / (1000 * 60 * 60 * 24) + 1; // Calculate days

            if (duration < 1) {
                alert("End date must be after start date.");
                return;
            }

            let taskRow = document.createElement("div");
            taskRow.classList.add("task-row");
            taskRow.innerHTML = `
                <span>${taskName}</span>
                <span>${startDate}</span>
                <span>${endDate}</span>
                <span>${duration} days</span>
                <progress value="0" max="${duration}" id="progress-${taskName}"></progress>
                <button onclick="removeTask(this)">❌</button>
            `;
            taskList.appendChild(taskRow);

            document.getElementById("task-name").value = "";
            document.getElementById("start-date").value = "";
            document.getElementById("end-date").value = "";
        }

        function removeTask(element) {
            element.parentElement.remove();
        }
    </script>
</body>
</html>
