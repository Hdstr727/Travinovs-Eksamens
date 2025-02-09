<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Schedule</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">

    <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full">
        <h2 class="text-2xl font-bold text-center text-[#e63946] mb-4">Weekly Schedule</h2>

        <!-- Select Day -->
        <div class="mb-4">
            <label class="block text-gray-700 font-medium mb-2">Select Day:</label>
            <select id="day-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
                <option value="Monday">Monday</option>
                <option value="Tuesday">Tuesday</option>
                <option value="Wednesday">Wednesday</option>
                <option value="Thursday">Thursday</option>
                <option value="Friday">Friday</option>
                <option value="Saturday">Saturday</option>
                <option value="Sunday">Sunday</option>
            </select>
        </div>

        <!-- Task Input -->
        <div class="mb-4 flex gap-2">
            <input type="text" id="task-input" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]" placeholder="Enter a task">
            <button onclick="addTask()" class="bg-[#e63946] text-white px-4 py-2 rounded-lg font-semibold hover:bg-red-700 transition">Add</button>
        </div>

        <!-- Task List -->
        <div id="schedule" class="mt-4">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Schedule Overview</h3>
            <div id="task-lists" class="space-y-4"></div>
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
                dayContainer.className = "bg-gray-200 p-3 rounded-lg shadow-md";
                dayContainer.innerHTML = `<h4 class="font-semibold text-[#e63946]">${selectedDay}</h4><ul class="list-disc pl-4 mt-2"></ul>`;
                taskLists.appendChild(dayContainer);
            }

            let li = document.createElement("li");
            li.className = "flex justify-between items-center bg-white px-3 py-2 rounded-lg shadow mt-2";
            li.innerHTML = `<span>${taskInput.value}</span> 
                            <button onclick="removeTask(this)" class="text-red-600 font-bold hover:text-red-800 transition">❌</button>`;

            dayContainer.querySelector("ul").appendChild(li);
            taskInput.value = "";
        }

        function removeTask(element) {
            element.parentElement.remove();
        }
    </script>
</body>
</html>
