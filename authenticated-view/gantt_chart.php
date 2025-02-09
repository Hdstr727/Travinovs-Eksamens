<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gantt Chart</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">

    <div class="bg-white p-6 rounded-lg shadow-lg max-w-4xl w-full">
        <h2 class="text-2xl font-bold text-center text-[#e63946] mb-4">Gantt Chart</h2>

        <!-- Task Input -->
        <div class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="text" id="task-name" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]" placeholder="Task Name">
                <input type="date" id="start-date" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
                <input type="date" id="end-date" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
                <button onclick="addTask()" class="bg-[#e63946] text-white px-4 py-2 rounded-lg font-semibold hover:bg-red-700 transition">Add Task</button>
            </div>
        </div>

        <!-- Gantt Chart Display -->
        <div class="overflow-x-auto">
            <div class="grid grid-cols-6 bg-gray-200 p-3 font-semibold rounded-t-lg text-gray-700">
                <span>Task</span>
                <span>Start Date</span>
                <span>End Date</span>
                <span>Duration</span>
                <span>Progress</span>
                <span>Action</span>
            </div>
            <div id="task-list" class="divide-y divide-gray-300"></div>
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
            taskRow.classList.add("grid", "grid-cols-6", "p-3", "bg-white", "rounded-lg", "shadow-sm", "items-center");
            taskRow.innerHTML = `
                <span class="text-gray-700">${taskName}</span>
                <span class="text-gray-600">${startDate}</span>
                <span class="text-gray-600">${endDate}</span>
                <span class="text-gray-600">${duration} days</span>
                <progress value="0" max="${duration}" id="progress-${taskName}" class="w-20"></progress>
                <button onclick="removeTask(this)" class="text-red-600 font-bold hover:text-red-800 transition">❌</button>
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
