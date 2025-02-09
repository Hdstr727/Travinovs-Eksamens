<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes & Ideas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center p-6">
    <div class="w-full max-w-3xl bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold text-[#e63946] mb-4">Notes & Ideas</h2>
        <p class="text-gray-600 mb-4">Keep all your notes, ideas, and reminders in one place.</p>

        <!-- Note Input -->
        <div class="flex space-x-2 mb-4">
            <input type="text" id="note-input" placeholder="Write a note..." class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
            <button onclick="addNote()" class="bg-[#e63946] text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">Add</button>
        </div>

        <!-- Notes List -->
        <div id="notes-list" class="space-y-3">
            <!-- Notes will appear here -->
        </div>
    </div>

    <script>
        let notes = JSON.parse(localStorage.getItem("notes")) || [];

        function addNote() {
            const noteInput = document.getElementById("note-input");
            const noteText = noteInput.value.trim();
            if (noteText === "") {
                alert("Please enter a note!");
                return;
            }

            const noteId = Date.now().toString();
            const note = { id: noteId, text: noteText };
            notes.push(note);
            noteInput.value = "";
            saveAndRenderNotes();
        }

        function editNote(id) {
            const newText = prompt("Edit your note:", notes.find(n => n.id === id).text);
            if (newText !== null) {
                notes = notes.map(n => (n.id === id ? { ...n, text: newText } : n));
                saveAndRenderNotes();
            }
        }

        function removeNote(id) {
            notes = notes.filter(n => n.id !== id);
            saveAndRenderNotes();
        }

        function saveAndRenderNotes() {
            localStorage.setItem("notes", JSON.stringify(notes));
            renderNotes();
        }

        function renderNotes() {
            const notesList = document.getElementById("notes-list");
            notesList.innerHTML = "";
            notes.forEach(note => {
                notesList.innerHTML += `
                    <div class="bg-gray-50 p-3 rounded-lg shadow-md flex items-center justify-between">
                        <p class="font-semibold">${note.text}</p>
                        <div class="flex space-x-2">
                            <button onclick="editNote('${note.id}')" class="text-blue-500 hover:text-blue-700">✏</button>
                            <button onclick="removeNote('${note.id}')" class="text-red-500 hover:text-red-700">❌</button>
                        </div>
                    </div>
                `;
            });
        }

        // Initial render
        renderNotes();
    </script>
</body>
</html>
