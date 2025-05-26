<?php
// File: authenticated-view/ajax_handlers/utils/update_board_activity.php

if (!function_exists('update_board_last_activity_timestamp')) {
    /**
     * Updates the 'updated_at' timestamp for a given board.
     *
     * Assumes that the calling script has already verified the user's permission
     * to perform an action on this board that warrants updating its activity timestamp.
     *
     * @param mysqli $connection The database connection object.
     * @param int $board_id The ID of the board to update.
     * @return bool True on success, false on failure.
     */
    function update_board_last_activity_timestamp(mysqli $connection, int $board_id): bool {
        if ($board_id <= 0) {
            error_log("update_board_last_activity_timestamp: Invalid board_id provided: $board_id");
            return false;
        }

        $sql = "UPDATE Planotajs_Boards SET updated_at = CURRENT_TIMESTAMP WHERE board_id = ?";
        $stmt = $connection->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("i", $board_id);
            if ($stmt->execute()) {
                $stmt->close();
                // error_log("update_board_last_activity_timestamp: Successfully updated timestamp for board_id: $board_id");
                return true;
            } else {
                error_log("update_board_last_activity_timestamp: Failed to execute timestamp update for board_id: $board_id. Error: " . $stmt->error);
            }
            $stmt->close(); // Ensure closed even on execute failure
        } else {
            error_log("update_board_last_activity_timestamp: Failed to prepare statement to update board timestamp for board_id: $board_id. Error: " . $connection->error);
        }
        return false;
    }
}
?>