<template>
    <div class="flex gap-4 p-6">
      <div v-for="status in ['planned', 'in_progress', 'completed']" :key="status" class="w-1/3 p-4 bg-gray-100 rounded">
        <h2 class="text-lg font-semibold capitalize">{{ formatStatus(status) }}</h2>
        <draggable v-model="tasks[status]" group="tasks" itemKey="id" @end="updateTask">
          <template #item="{ element }">
            <div class="p-2 bg-white rounded shadow cursor-pointer">
              {{ element.title }}
            </div>
          </template>
        </draggable>
      </div>
    </div>
  </template>
  
  <script>
  import draggable from "vuedraggable";
  import axios from "axios";
  
  export default {
    components: { draggable },
    data() {
      return { 
        tasks: { planned: [], in_progress: [], completed: [] } 
      };
    },
    async mounted() {
      try {
        const response = await axios.get("/api/tasks");
        console.log("API Response:", response.data); // Debugging
        
        if (!Array.isArray(response.data)) {
          console.error("Invalid API response, expected an array:", response.data);
          return;
        }
  
        this.tasks = { planned: [], in_progress: [], completed: [] };
        response.data.forEach(task => {
          if (this.tasks[task.status]) {
            this.tasks[task.status].push(task);
          }
        });
  
      } catch (error) {
        console.error("Error fetching tasks:", error.response ? error.response.data : error);
      }
    },
    methods: {
      async updateTask(event) {
        try {
          const task = this.tasks[event.to.dataset.status][event.newIndex];
          console.log(`Updating task ${task.id} to status ${event.to.dataset.status}`);
  
          await axios.put(`/api/tasks/${task.id}`, { status: event.to.dataset.status });
        } catch (error) {
          console.error("Error updating task:", error.response ? error.response.data : error);
        }
      },
      formatStatus(status) {
        return status.replace("_", " ").toUpperCase();
      }
    }
  };
  </script>
  
  <style scoped>
  .cursor-pointer {
    cursor: pointer;
  }
  </style>
  