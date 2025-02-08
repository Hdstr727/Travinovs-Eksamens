<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Schedule</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="schedule-container">
        <h2>Weekly Schedule</h2>

        <!-- Select Day -->
        <label>Select Day:</label>
        <select id="day-select">
            <option value="Monday">Monday</option>
            <option value="Tuesday">Tuesday</option>
            <option value="Wednesday">Wednesday</option>
            <option value="Thursday">Thursday</option>
            <option value="Friday">Friday</option>
            <option value="Saturday">Saturday</option>
            <option value="Sunday">Sunday</option>
        </select>

        <!-- Task Input -->
        <input type="text" id="task-input" placeholder="Enter a task">
        <button onclick="addTask()">Add Task</button>

        <!-- Task List -->
        <div id="schedule">
            <h3>Schedule Overview</h3>
            <div id="task-lists"></div>
        </div>
    </div>

    <script>
        function addTask() {
            let taskInput = document.getElementById("task-input");
            let selectedDay = document.getElementById("day-select").value;
            let taskLists = document.getElementById("task-lists");

            if (taskInput.value.trim() === "") {
                alert("Please enter a task.");
                return;
            }

            let dayContainer = document.getElementById(selectedDay);

            if (!dayContainer) {
                dayContainer = document.createElement("div");
                dayContainer.id = selectedDay;
                dayContainer.innerHTML = `<h4>${selectedDay}</h4><ul></ul>`;
                taskLists.appendChild(dayContainer);
            }

            let li = document.createElement("li");
            li.innerHTML = `${taskInput.value} <button onclick="removeTask(this)">❌</button>`;
            dayContainer.querySelector("ul").appendChild(li);

            taskInput.value = "";
        }

        function removeTask(element) {
            element.parentElement.remove();
        }
    </script>
</body>
</html>
