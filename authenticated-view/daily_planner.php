<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">

    <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full">
        <h2 class="text-2xl font-bold text-center text-[#e63946] mb-4">Daily Planner</h2>

        <!-- Date Picker -->
        <div class="mb-4">
            <label class="block text-gray-700 font-medium mb-2">Select Date:</label>
            <input type="date" id="task-date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
        </div>

        <!-- Task Input -->
        <div class="mb-4 flex gap-2">
            <input type="text" id="task-input" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]" placeholder="Enter a task">
            <button onclick="addTask()" class="bg-[#e63946] text-white px-4 py-2 rounded-lg font-semibold hover:bg-red-700 transition">Add</button>
        </div>

        <!-- Task List -->
        <ul id="task-list" class="space-y-2"></ul>
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
            li.className = "flex justify-between items-center bg-gray-200 px-4 py-2 rounded-lg shadow";

            li.innerHTML = `<span>${taskInput.value} - <strong>${taskDate}</strong></span> 
                            <button onclick="removeTask(this)" class="text-red-600 font-bold hover:text-red-800 transition">❌</button>`;

            taskList.appendChild(li);
            taskInput.value = "";
        }

        function removeTask(element) {
            element.parentElement.remove();
        }
    </script>
</body>
</html>
