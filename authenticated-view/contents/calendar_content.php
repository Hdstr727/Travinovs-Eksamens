<?php
// calendar_content.php - Will be included in the main layout

// Determine the current view
$view = isset($_GET['view']) ? $_GET['view'] : 'month'; // Default to month

// --- Date Initialization ---
$current_timestamp = time();
$today_date_str = date('Y-m-d', $current_timestamp);
$today_month = (int)date('m', $current_timestamp);
$today_year = (int)date('Y', $current_timestamp);

// Month View specific date parameters
// Default to today's month/year if not provided
$month_param_from_get = isset($_GET['month']) ? (int)$_GET['month'] : $today_month;
$year_param_from_get = isset($_GET['year']) ? (int)$_GET['year'] : $today_year;

// Week/Day View specific date parameter (a reference date string like 'Y-m-d')
// Default to today's date if not provided
$date_param_str = isset($_GET['date']) ? $_GET['date'] : $today_date_str;

try {
    // Ensure $date_param_str is a valid date, fallback to today if not
    $reference_timestamp = strtotime($date_param_str);
    if ($reference_timestamp === false) {
        $reference_timestamp = $current_timestamp;
        $date_param_str = $today_date_str; // Correct $date_param_str if it was invalid
    }
} catch (Exception $e) {
    $reference_timestamp = $current_timestamp;
    $date_param_str = $today_date_str;
}


// Initialize variables for SQL query, page title, and navigation
$start_date_sql = '';
$end_date_sql = '';
$page_title = '';
$nav_prev_params_array = ['view' => $view];
$nav_next_params_array = ['view' => $view];
$nav_today_params_array = ['view' => $view]; // Initialize today params with current view

$days_to_display_in_grid = []; // For month/week views

// --- VIEW-SPECIFIC LOGIC ---
if ($view === 'month') {
    $current_month = $month_param_from_get;
    $current_year = $year_param_from_get;

    // Validate month and year for month view
    if ($current_month < 1) { $current_month = 12; $current_year--; }
    if ($current_month > 12) { $current_month = 1; $current_year++; }

    $first_day_of_month_timestamp = mktime(0, 0, 0, $current_month, 1, $current_year);
    $days_in_month = (int)date('t', $first_day_of_month_timestamp);
    $first_day_of_week_for_month = (int)date('N', $first_day_of_month_timestamp); // 1 (Mon) - 7 (Sun)

    $month_name_display = date('F', $first_day_of_month_timestamp);
    $page_title = htmlspecialchars(ucfirst($month_name_display)) . " " . $current_year;

    $start_date_sql = date('Y-m-d', $first_day_of_month_timestamp);
    $end_date_sql = date('Y-m-d', mktime(0, 0, 0, $current_month + 1, 0, $current_year));

    $nav_prev_params_array['month'] = $current_month - 1;
    $nav_prev_params_array['year'] = $current_year;
    $nav_next_params_array['month'] = $current_month + 1;
    $nav_next_params_array['year'] = $current_year;
    $nav_today_params_array['month'] = $today_month;
    $nav_today_params_array['year'] = $today_year;

} elseif ($view === 'week') {
    // For week view, $reference_timestamp (derived from $_GET['date'] or $today_date_str)
    // is a day within the target week.
    $day_of_week_ref = (int)date('N', $reference_timestamp); // 1 (Mon) to 7 (Sun)
    // Calculate Monday of that week
    $start_of_week_timestamp = strtotime("-" . ($day_of_week_ref - 1) . " days", $reference_timestamp);
    $start_of_week_timestamp = mktime(0,0,0, date('m',$start_of_week_timestamp), date('d',$start_of_week_timestamp), date('Y',$start_of_week_timestamp));
    // Calculate Sunday of that week
    $end_of_week_timestamp = strtotime("+6 days", $start_of_week_timestamp);
    $end_of_week_timestamp = mktime(23,59,59, date('m',$end_of_week_timestamp), date('d',$end_of_week_timestamp), date('Y',$end_of_week_timestamp));

    $start_date_sql = date('Y-m-d', $start_of_week_timestamp);
    $end_date_sql = date('Y-m-d', $end_of_week_timestamp);

    $page_title = "Week: " . date('M j, Y', $start_of_week_timestamp) . " - " . date('M j, Y', $end_of_week_timestamp);

    for ($i = 0; $i < 7; $i++) {
        $day_timestamp = strtotime("+$i days", $start_of_week_timestamp);
        $current_day_str_loop = date('Y-m-d', $day_timestamp);
        $days_to_display_in_grid[$current_day_str_loop] = [
            'day_number' => date('d', $day_timestamp),
            'date_str' => $current_day_str_loop,
            'is_today' => ($current_day_str_loop == $today_date_str)
        ];
    }
    $nav_prev_params_array['date'] = date('Y-m-d', strtotime("-7 days", $start_of_week_timestamp));
    $nav_next_params_array['date'] = date('Y-m-d', strtotime("+7 days", $start_of_week_timestamp));
    $nav_today_params_array['date'] = $today_date_str;

} elseif ($view === 'day') {
    // For day view, $reference_timestamp (derived from $_GET['date'] or $today_date_str) is the target day.
    $start_date_sql = date('Y-m-d', $reference_timestamp);
    $end_date_sql = date('Y-m-d', $reference_timestamp);

    $page_title = "Day: " . date('F j, Y', $reference_timestamp);

    $current_day_str_display = date('Y-m-d', $reference_timestamp);
    $days_to_display_in_grid[$current_day_str_display] = [
        'date_str' => $current_day_str_display,
        'is_today' => ($current_day_str_display == $today_date_str),
        'day_name_long' => date('l', $reference_timestamp)
    ];

    $nav_prev_params_array['date'] = date('Y-m-d', strtotime("-1 day", $reference_timestamp));
    $nav_next_params_array['date'] = date('Y-m-d', strtotime("+1 day", $reference_timestamp));
    $nav_today_params_array['date'] = $today_date_str;
}

// Convert navigation parameter arrays to query strings
$nav_prev_params = http_build_query($nav_prev_params_array);
$nav_next_params = http_build_query($nav_next_params_array);
$nav_today_params = http_build_query($nav_today_params_array);


// --- SQL Query to fetch tasks (common for all views) ---
$sql = "SELECT t.*, b.board_name 
        FROM Planotajs_Tasks t 
        LEFT JOIN Planotajs_Boards b ON t.board_id = b.board_id 
        WHERE DATE(t.due_date) BETWEEN ? AND ? 
        AND t.is_deleted = 0";
$stmt = $connection->prepare($sql);
$stmt->bind_param("ss", $start_date_sql, $end_date_sql);
$stmt->execute();
$result = $stmt->get_result();

$tasks_by_date = [];
while ($task = $result->fetch_assoc()) {
    $date_key = date('Y-m-d', strtotime($task['due_date']));
    if (!isset($tasks_by_date[$date_key])) {
        $tasks_by_date[$date_key] = [];
    }
    $tasks_by_date[$date_key][] = $task;
}
$stmt->close();

// Helper function to render tasks for a specific day
function render_tasks_for_day($date_str_for_tasks, $tasks_by_date_array) {
    $output = '';
    if (isset($tasks_by_date_array[$date_str_for_tasks])) {
        foreach ($tasks_by_date_array[$date_str_for_tasks] as $task) {
            $priority_class = '';
            switch (strtolower($task['priority'])) {
                case 'high': $priority_class = 'priority-high'; break;
                case 'medium': $priority_class = 'priority-medium'; break;
                case 'low': $priority_class = 'priority-low'; break;
                default: $priority_class = '';
            }
            $status_class = ($task['is_completed'] == 1) ? 'task-completed' : '';
            $output .= '<div class="task-item ' . htmlspecialchars($priority_class) . ' ' . htmlspecialchars($status_class) . '" 
                            data-task-id="' . htmlspecialchars($task['task_id']) . '" 
                            onclick="showTaskDetails(this, event)">
                            <span class="truncate block">' . htmlspecialchars($task['task_name']) . '</span>
                        </div>';
        }
    }
    return $output;
}
?>

<!-- Calendar CSS -->
<style>
    .calendar-day { min-height: 100px; position: relative; }
    .task-item { font-size: 0.8rem; padding: 4px 8px; margin-bottom: 3px; border-radius: 3px; cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; transition: all 0.2s; }
    .task-item:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .priority-high { border-left: 3px solid #ef4444; background-color: rgba(239, 68, 68, 0.1); }
    .priority-medium { border-left: 3px solid #f59e0b; background-color: rgba(245, 158, 11, 0.1); }
    .priority-low { border-left: 3px solid #22c55e; background-color: rgba(34, 197, 94, 0.1); }
    .task-completed { text-decoration: line-through; opacity: 0.6; }
    .today { background-color: rgba(230, 57, 70, 0.1); }
    .task-popup { display: none; position: absolute; background: white; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 100; min-width: 250px; max-width: 300px; }
    .day-number { position: absolute; top: 4px; right: 6px; font-size: 0.9rem; font-weight: 500; color: #666; }
</style>

<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Task Calendar</h2>
        <div class="flex items-center gap-3">
            <?php
            // CORRECTED: These links will now always take you to the respective view for the *actual current* day/week/month
            $day_link_params_switcher = ['view' => 'day', 'date' => $today_date_str];
            $week_link_params_switcher = ['view' => 'week', 'date' => $today_date_str]; // Date used to find current week
            $month_link_params_switcher = ['view' => 'month', 'month' => $today_month, 'year' => $today_year];
            ?>
            <a href="?<?= http_build_query($day_link_params_switcher) ?>" class="p-2 rounded-lg hover:bg-gray-100 <?= ($view == 'day') ? 'bg-gray-200 font-semibold text-gray-800' : 'text-gray-600' ?>">
                <i class="fas fa-calendar-day"></i> Day
            </a>
            <a href="?<?= http_build_query($week_link_params_switcher) ?>" class="p-2 rounded-lg hover:bg-gray-100 <?= ($view == 'week') ? 'bg-gray-200 font-semibold text-gray-800' : 'text-gray-600' ?>">
                <i class="fas fa-calendar-week"></i> Week
            </a>
            <a href="?<?= http_build_query($month_link_params_switcher) ?>" class="p-2 rounded-lg hover:bg-gray-100 <?= ($view == 'month') ? 'bg-gray-200 font-semibold text-gray-800' : 'text-gray-600' ?>">
                <i class="fas fa-calendar-alt"></i> Month
            </a>
        </div>
    </div>
    
    <!-- Navigation and Title -->
    <div class="flex justify-between items-center mb-6">
        <a href="?<?= $nav_prev_params ?>" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg flex items-center gap-1">
            <i class="fas fa-chevron-left"></i> Previous
        </a>
        <div class="flex items-center gap-2">
            <h3 class="text-xl font-semibold"><?= $page_title ?></h3>
            <a href="?<?= $nav_today_params ?>" class="ml-3 text-sm text-blue-600 hover:underline">Today</a>
        </div>
        <a href="?<?= $nav_next_params ?>" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg flex items-center gap-1">
            Next <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    
    <?php if ($view === 'month'): ?>
        <!-- Calendar Grid - MONTH VIEW -->
        <div class="grid grid-cols-7 gap-px bg-gray-200 border border-gray-200 rounded-lg overflow-hidden shadow-sm">
            <?php $day_headers = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']; ?>
            <?php foreach ($day_headers as $header): ?>
                <div class="text-center font-semibold p-2 bg-white"><?= $header ?></div>
            <?php endforeach; ?>
            
            <?php 
            for ($i = 1; $i < $first_day_of_week_for_month; $i++) {
                echo '<div class="bg-gray-50 calendar-day opacity-50"></div>';
            }
            for ($day_iter = 1; $day_iter <= $days_in_month; $day_iter++) {
                $date_str_iter = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day_iter);
                $is_today_iter = ($date_str_iter == $today_date_str);
                $day_class = $is_today_iter ? 'bg-white calendar-day today' : 'bg-white calendar-day';
                
                echo '<div class="' . $day_class . ' p-2 relative">';
                echo '<span class="day-number">' . $day_iter . '</span>';
                echo render_tasks_for_day($date_str_iter, $tasks_by_date);
                echo '</div>';
            }
            $last_day_of_week_for_month = (int)date('N', mktime(0, 0, 0, $current_month, $days_in_month, $current_year));
            for ($i = $last_day_of_week_for_month + 1; $i <= 7; $i++) {
                echo '<div class="bg-gray-50 calendar-day opacity-50"></div>';
            }
            ?>
        </div>

    <?php elseif ($view === 'week'): ?>
        <!-- Calendar Grid - WEEK VIEW -->
        <div class="grid grid-cols-7 gap-px bg-gray-200 border border-gray-200 rounded-lg overflow-hidden shadow-sm">
            <?php $day_headers = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']; ?>
            <?php foreach ($day_headers as $header): ?>
                <div class="text-center font-semibold p-2 bg-white"><?= $header ?></div>
            <?php endforeach; ?>

            <?php foreach ($days_to_display_in_grid as $date_key => $day_data): ?>
                <?php
                $day_class = $day_data['is_today'] ? 'bg-white calendar-day today' : 'bg-white calendar-day';
                ?>
                <div class="<?= $day_class ?> p-2 relative">
                    <span class="day-number"><?= $day_data['day_number'] ?></span>
                    <?= render_tasks_for_day($day_data['date_str'], $tasks_by_date) ?>
                </div>
            <?php endforeach; ?>
        </div>
    
    <?php elseif ($view === 'day'): ?>
        <!-- Day View -->
        <div class="bg-white p-4 rounded-lg shadow">
            <?php foreach ($days_to_display_in_grid as $date_key => $day_data): ?>
                <h4 class="text-lg font-semibold mb-3"><?= htmlspecialchars($day_data['day_name_long']) ?>, <?= date('M j', strtotime($day_data['date_str'])) ?></h4>
                <?php
                $tasks_html_for_day = render_tasks_for_day($day_data['date_str'], $tasks_by_date);
                if (empty($tasks_html_for_day)) {
                    echo '<p class="text-gray-500">No tasks scheduled for this day.</p>';
                } else {
                    echo '<div class="space-y-2">' . $tasks_html_for_day . '</div>';
                }
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Task Popup -->
    <div id="taskPopup" class="task-popup p-4">
        <div class="flex justify-between items-start mb-3">
            <h4 id="popupTaskName" class="font-bold text-lg"></h4>
            <button onclick="hideTaskPopup()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-2">
            <span class="text-sm text-gray-500">Description:</span>
            <p id="popupTaskDescription" class="text-sm"></p>
        </div>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <div><span class="text-gray-500">Due Date:</span><p id="popupTaskDueDate"></p></div>
            <div><span class="text-gray-500">Priority:</span><p id="popupTaskPriority"></p></div>
            <div><span class="text-gray-500">Status:</span><p id="popupTaskStatus"></p></div>
            <div><span class="text-gray-500">Board:</span><p id="popupTaskBoard"></p></div>
        </div>
        <div class="mt-4 flex justify-end">
            <a id="popupViewLink" href="#" class="text-blue-600 hover:underline">View on Board</a>
        </div>
    </div>
</div>

<!-- Task popup functionality -->
<script>
    const taskPopup = document.getElementById('taskPopup');
    let currentTaskId = null;
    
    function showTaskDetails(element, event) {
        event.preventDefault();
        event.stopPropagation();
        const taskId = element.dataset.taskId;
        currentTaskId = taskId;
        
        const rect = element.getBoundingClientRect();
        let popupTop = rect.bottom + window.scrollY + 5;
        let popupLeft = rect.left + window.scrollX;
        taskPopup.style.display = 'block';

        const popupWidth = taskPopup.offsetWidth || 250;
        const popupHeight = taskPopup.offsetHeight || 200;

        if (popupLeft + popupWidth > window.innerWidth) { popupLeft = window.innerWidth - popupWidth - 10; }
        if (popupTop + popupHeight > window.innerHeight + window.scrollY) { popupTop = rect.top + window.scrollY - popupHeight - 5; }
        if (popupLeft < 0) { popupLeft = 10; }
        if (popupTop < window.scrollY) { popupTop = window.scrollY + 10; }

        taskPopup.style.left = `${popupLeft}px`;
        taskPopup.style.top = `${popupTop}px`;
        
        fetch(`contents/get_task_details.php?task_id=${taskId}`)
            .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                return response.json();
            })
            .then(task => {
                document.getElementById('popupTaskName').textContent = task.task_name || 'Task';
                document.getElementById('popupTaskDescription').textContent = task.task_description || 'No description';
                document.getElementById('popupTaskDueDate').textContent = task.due_date ? formatDate(task.due_date) : 'Not set';
                
                let priorityText = 'Not specified';
                if (task.priority) {
                    const priority = task.priority.toLowerCase();
                    if (priority === 'high') priorityText = 'High';
                    else if (priority === 'medium') priorityText = 'Medium';
                    else if (priority === 'low') priorityText = 'Low';
                }
                document.getElementById('popupTaskPriority').textContent = priorityText;
                
                let statusText = task.is_completed == 1 ? 'Completed' : (task.task_status || 'Pending');
                document.getElementById('popupTaskStatus').textContent = statusText;
                document.getElementById('popupTaskBoard').textContent = task.board_name || 'Not specified';
                document.getElementById('popupViewLink').href = `kanban.php?board_id=${task.board_id}`;
            })
            .catch(error => {
                console.error('Error fetching task details:', error);
                alert('Could not load task details.');
                if (taskPopup) taskPopup.style.display = 'none';
            });
    }
    
    function hideTaskPopup() {
        if (taskPopup) taskPopup.style.display = 'none';
        currentTaskId = null;
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const dateObj = new Date(dateString);
        if (isNaN(dateObj.getTime())) return 'Invalid Date';
        return dateObj.toLocaleDateString('lv-LV', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
    
    document.addEventListener('click', function(event) {
        if (taskPopup && taskPopup.style.display === 'block' && !taskPopup.contains(event.target) && !event.target.closest('.task-item')) {
            hideTaskPopup();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape" && taskPopup && taskPopup.style.display === 'block') {
            hideTaskPopup();
        }
    });
</script>