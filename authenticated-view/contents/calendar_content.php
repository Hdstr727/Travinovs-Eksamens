<?php
// calendar_content.php - Will be included in the main layout

// Get current month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Validate month and year
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

// Get first day of the month
$first_day = mktime(0, 0, 0, $month, 1, $year);
$first_day_of_week = date('N', $first_day); // 1 (for Monday) through 7 (for Sunday)
$days_in_month = date('t', $first_day);

// Get month name
$month_name = date('F', $first_day);

// Get tasks for the current month
$start_date = date('Y-m-d', $first_day);
$end_date = date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, $year));

// Fixed SQL query to remove board_color
$sql = "SELECT t.*, b.board_name 
        FROM Planotajs_Tasks t 
        LEFT JOIN Planotajs_Boards b ON t.board_id = b.board_id 
        WHERE t.due_date BETWEEN ? AND ? 
        AND t.is_deleted = 0";
$stmt = $connection->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// Organize tasks by date
$tasks_by_date = [];
while ($task = $result->fetch_assoc()) {
    $date = date('Y-m-d', strtotime($task['due_date']));
    if (!isset($tasks_by_date[$date])) {
        $tasks_by_date[$date] = [];
    }
    $tasks_by_date[$date][] = $task;
}
$stmt->close();
?>

<!-- Calendar CSS -->
<style>
    .calendar-day {
        min-height: 100px;
        position: relative;
    }
    .task-item {
        font-size: 0.8rem;
        padding: 4px 8px;
        margin-bottom: 3px;
        border-radius: 3px;
        cursor: pointer;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        transition: all 0.2s;
    }
    .task-item:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .priority-high { border-left: 3px solid #ef4444; background-color: rgba(239, 68, 68, 0.1); }
    .priority-medium { border-left: 3px solid #f59e0b; background-color: rgba(245, 158, 11, 0.1); }
    .priority-low { border-left: 3px solid #22c55e; background-color: rgba(34, 197, 94, 0.1); }
    .task-completed { text-decoration: line-through; opacity: 0.6; }
    .today { background-color: rgba(230, 57, 70, 0.1); }
    .task-popup {
        display: none;
        position: absolute;
        background: white;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 100;
        min-width: 250px;
        max-width: 300px;
    }
    .day-number {
        position: absolute;
        top: 4px;
        right: 6px;
        font-size: 0.9rem;
        font-weight: 500;
        color: #666;
    }
</style>

<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Uzdevumu kalendārs</h2>
        <div class="flex items-center gap-3">
            <a href="?view=day" class="p-2 rounded-lg hover:bg-gray-100 <?= (!isset($_GET['view']) || $_GET['view'] == 'month') ? '' : 'bg-gray-100' ?>">
                <i class="fas fa-calendar-day"></i> Diena
            </a>
            <a href="?view=week" class="p-2 rounded-lg hover:bg-gray-100 <?= (isset($_GET['view']) && $_GET['view'] == 'week') ? 'bg-gray-100' : '' ?>">
                <i class="fas fa-calendar-week"></i> Nedēļa
            </a>
            <a href="?view=month" class="p-2 rounded-lg hover:bg-gray-100 <?= (!isset($_GET['view']) || $_GET['view'] == 'month') ? 'bg-gray-100' : '' ?>">
                <i class="fas fa-calendar-alt"></i> Mēnesis
            </a>
        </div>
    </div>
    
    <!-- Month Navigation -->
    <div class="flex justify-between items-center mb-6">
        <a href="?month=<?= $month-1 ?>&year=<?= $year ?>" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg flex items-center gap-1">
            <i class="fas fa-chevron-left"></i> Iepriekšējais
        </a>
        <div class="flex items-center gap-2">
            <h3 class="text-xl font-semibold"><?= $month_name ?> <?= $year ?></h3>
            <a href="?month=<?= date('m') ?>&year=<?= date('Y') ?>" class="ml-3 text-sm text-blue-600 hover:underline">Šodien</a>
        </div>
        <a href="?month=<?= $month+1 ?>&year=<?= $year ?>" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg flex items-center gap-1">
            Nākamais <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    
    <!-- Calendar Grid -->
    <div class="grid grid-cols-7 gap-px bg-gray-200 border border-gray-200 rounded-lg overflow-hidden shadow-sm">
        <div class="text-center font-semibold p-2 bg-white">Pir</div>
        <div class="text-center font-semibold p-2 bg-white">Otr</div>
        <div class="text-center font-semibold p-2 bg-white">Tre</div>
        <div class="text-center font-semibold p-2 bg-white">Cet</div>
        <div class="text-center font-semibold p-2 bg-white">Pie</div>
        <div class="text-center font-semibold p-2 bg-white">Ses</div>
        <div class="text-center font-semibold p-2 bg-white">Svē</div>
        
        <?php 
        // Add empty cells for days before the first day of month
        for ($i = 1; $i < $first_day_of_week; $i++) {
            echo '<div class="bg-gray-50 calendar-day opacity-50"></div>';
        }
        
        // Days of the month
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $is_today = ($date == date('Y-m-d'));
            $day_class = $is_today ? 'bg-white calendar-day today' : 'bg-white calendar-day';
            
            echo '<div class="' . $day_class . ' p-2 relative">';
            echo '<span class="day-number">' . $day . '</span>';
            
            // Display tasks for this day
            if (isset($tasks_by_date[$date])) {
                foreach ($tasks_by_date[$date] as $task) {
                    $priority_class = '';
                    // Handle text-based priority values
                    switch (strtolower($task['priority'])) {
                        case 'high':
                            $priority_class = 'priority-high';
                            break;
                        case 'medium':
                            $priority_class = 'priority-medium';
                            break;
                        case 'low':
                            $priority_class = 'priority-low';
                            break;
                        default:
                            $priority_class = '';  // Default case for when priority is empty or null
                    }
                    
                    $status_class = ($task['is_completed'] == 1) ? 'task-completed' : '';
                    
                    echo '<div class="task-item ' . $priority_class . ' ' . $status_class . '" 
                            data-task-id="' . $task['task_id'] . '" 
                            onclick="showTaskDetails(this, event)">
                            <span class="truncate block">' . htmlspecialchars($task['task_name']) . '</span>
                        </div>';
                }
            }
            
            echo '</div>';
        }
        
        // Add empty cells for days after the last day of month
        $last_day_of_week = date('N', mktime(0, 0, 0, $month, $days_in_month, $year));
        for ($i = $last_day_of_week + 1; $i <= 7; $i++) {
            echo '<div class="bg-gray-50 calendar-day opacity-50"></div>';
        }
        ?>
    </div>
    
    <!-- Task Popup -->
    <div id="taskPopup" class="task-popup p-4">
        <div class="flex justify-between items-start mb-3">
            <h4 id="popupTaskName" class="font-bold text-lg"></h4>
            <button onclick="hideTaskPopup()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-2">
            <span class="text-sm text-gray-500">Apraksts:</span>
            <p id="popupTaskDescription" class="text-sm"></p>
        </div>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <div>
                <span class="text-gray-500">Termiņš:</span>
                <p id="popupTaskDueDate"></p>
            </div>
            <div>
                <span class="text-gray-500">Prioritāte:</span>
                <p id="popupTaskPriority"></p>
            </div>
            <div>
                <span class="text-gray-500">Statuss:</span>
                <p id="popupTaskStatus"></p>
            </div>
            <div>
                <span class="text-gray-500">Dēlis:</span>
                <p id="popupTaskBoard"></p>
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <a id="popupEditLink" href="#" class="text-blue-600 hover:underline mr-4">Rediģēt</a>
            <a id="popupViewLink" href="#" class="text-blue-600 hover:underline">Skatīt dēlī</a>
        </div>
    </div>
</div>

<!-- Task popup functionality -->
<script>
    // Task details popup functionality
    const taskPopup = document.getElementById('taskPopup');
    let currentTaskId = null;
    
    function showTaskDetails(element, event) {
    event.preventDefault();
    event.stopPropagation();
    
    const taskId = element.dataset.taskId;
    currentTaskId = taskId;
    
    // Position the popup
    const rect = element.getBoundingClientRect();
    taskPopup.style.left = `${rect.left}px`;
    taskPopup.style.top = `${rect.bottom + window.scrollY + 5}px`;
    
    // Fetch task details via AJAX
    fetch(`get_task_details.php?task_id=${taskId}`)
        .then(response => response.json())
        .then(task => {
            document.getElementById('popupTaskName').textContent = task.task_name;
            document.getElementById('popupTaskDescription').textContent = task.task_description || 'Nav apraksta';
            document.getElementById('popupTaskDueDate').textContent = formatDate(task.due_date);
            
            // Handle text-based priority values
            let priorityText = 'Nav norādīta';
            if (task.priority) {
                // Convert to lowercase for case-insensitive comparison
                const priority = task.priority.toLowerCase();
                
                switch(priority) {
                    case 'high': priorityText = 'Augsta'; break;
                    case 'medium': priorityText = 'Vidēja'; break;
                    case 'low': priorityText = 'Zema'; break;
                }
            }
            
            document.getElementById('popupTaskPriority').textContent = priorityText;
            
            document.getElementById('popupTaskStatus').textContent = task.is_completed == 1 ? 'Pabeigts' : task.task_status;
            document.getElementById('popupTaskBoard').textContent = task.board_name || 'Nav norādīts';
            
            document.getElementById('popupEditLink').href = `edit_task.php?task_id=${taskId}`;
            document.getElementById('popupViewLink').href = `kanban.php?board_id=${task.board_id}`;
            
            taskPopup.style.display = 'block';
        })
        .catch(error => {
            console.error('Error fetching task details:', error);
        });
    }
    
    function hideTaskPopup() {
        taskPopup.style.display = 'none';
        currentTaskId = null;
    }
    
    // Format date for display
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('lv-LV', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // Close popup when clicking outside
    document.addEventListener('click', function(event) {
        if (taskPopup.style.display === 'block' && !taskPopup.contains(event.target)) {
            hideTaskPopup();
        }
    });
</script>