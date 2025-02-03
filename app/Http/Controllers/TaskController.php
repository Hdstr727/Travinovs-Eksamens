<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index()
    {
        return Task::where('user_id', auth()->id())->get();
    }

    public function store(Request $request)
    {
        $task = Task::create([
            'title' => $request->title,
            'description' => $request->description,
            'status' => 'planned',
            'user_id' => auth()->id(),
        ]);
        return response()->json($task, 201);
    }

    public function update(Request $request, Task $task)
    {
        $task->update($request->only(['title', 'description', 'status']));
        return response()->json($task);
    }

    public function destroy(Task $task)
    {
        $task->delete();
        return response()->json(['message' => 'Task deleted']);
    }

}
