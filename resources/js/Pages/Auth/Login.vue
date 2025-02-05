<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-100">
      <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Pieteikšanās</h2>
        <form @submit.prevent="login">
          <div class="mb-4">
            <label class="block text-gray-700">E-pasts</label>
            <input v-model="form.email" type="email" class="input-field" required />
          </div>
          <div class="mb-4">
            <label class="block text-gray-700">Parole</label>
            <input v-model="form.password" type="password" class="input-field" required />
          </div>
          <button type="submit" class="btn">Pieteikties</button>
          <p class="mt-4 text-center">
            Nav konta? <router-link to="/register" class="text-red-500">Reģistrēties</router-link>
          </p>
        </form>
      </div>
    </div>
  </template>
  
  <script>
  import axios from "axios";
  import { useRouter } from "vue-router";
  
  export default {
    setup() {
      const router = useRouter();
      const form = { email: "", password: "" };
  
      const login = async () => {
        try {
          await axios.post("/login", form);
          router.push("/kanban");
        } catch (error) {
          console.error("Login failed", error.response?.data);
        }
      };
  
      return { form, login };
    }
  };
  </script>
  
  <style scoped>
  .input-field {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
  }
  .btn {
    width: 100%;
    background-color: #e63946;
    color: white;
    padding: 10px;
    border-radius: 5px;
    font-weight: bold;
  }
  </style>
  