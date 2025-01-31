<div x-data="{ open: false }">
    <button @click="open = !open">Добавить задачу</button>
    
    <form x-show="open" method="POST" action="{{ route('tasks.store') }}">
        @csrf
        <input type="text" name="title" placeholder="Название задачи" required>
        <button type="submit">Создать</button>
    </form>
</div>
