<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Planner</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="planner-container">
        <h2>Daily Planner</h2>

        <!-- Date Picker -->
        <label>Select Date:</label>
        <input type="date" id="task-date" value="">
        
        <!-- Task Input -->
        <input type="text" id="task-input" placeholder="Enter a task">
        <button onclick="addTask()">Add Task</button>

        <!-- Task List -->
        <ul id="task-list"></ul>
    </div>

    <script>
        function addTask() {
            let taskInput = document.getElementById("task-input");
            let taskDate = document.getElementById("task-date").value;
            let taskList = document.getElementById("task-list");

            if (taskInput.value.trim() === "" || taskDate === "") {
                alert("Please enter a task and select a date.");
                return;
            }

            let li = document.createElement("li");
            li.innerHTML = `${taskInput.value} - <strong>${taskDate}</strong> <button onclick="removeTask(this)">❌</button>`;
            taskList.appendChild(li);

            taskInput.value = "";
        }

        function removeTask(element) {
            element.parentElement.remove();
        }
    </script>
</body>
</html>
