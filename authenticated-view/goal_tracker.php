<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-2xl bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold text-[#e63946] mb-4">Goal Tracker</h2>

        <!-- Goal Input -->
        <div class="flex space-x-2 mb-4">
            <input type="text" id="goal-input" placeholder="Enter a goal..." class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
            <button onclick="addGoal()" class="bg-[#e63946] text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">Add</button>
        </div>

        <!-- Goal List -->
        <div id="goal-list" class="space-y-3">
            <!-- Goals will appear here -->
        </div>
    </div>

    <script>
        let goals = [];

        function addGoal() {
            const goalInput = document.getElementById("goal-input");
            const goalText = goalInput.value.trim();
            if (goalText === "") {
                alert("Please enter a goal!");
                return;
            }

            const goalId = Date.now().toString();
            const goal = { id: goalId, text: goalText, progress: 0 };
            goals.push(goal);
            goalInput.value = "";
            renderGoals();
        }

        function updateProgress(id, value) {
            const goal = goals.find(g => g.id === id);
            if (goal) {
                goal.progress = value;
                renderGoals();
            }
        }

        function removeGoal(id) {
            goals = goals.filter(g => g.id !== id);
            renderGoals();
        }

        function renderGoals() {
            const goalList = document.getElementById("goal-list");
            goalList.innerHTML = "";
            goals.forEach(goal => {
                goalList.innerHTML += `
                    <div class="bg-gray-50 p-3 rounded-lg shadow-md flex items-center justify-between">
                        <div>
                            <p class="font-semibold">${goal.text}</p>
                            <input type="range" min="0" max="100" value="${goal.progress}" class="w-full mt-2" oninput="updateProgress('${goal.id}', this.value)">
                            <p class="text-sm text-gray-500">${goal.progress}% completed</p>
                        </div>
                        <button onclick="removeGoal('${goal.id}')" class="text-red-500 hover:text-red-700">❌</button>
                    </div>
                `;
            });
        }
    </script>
</body>
</html>
