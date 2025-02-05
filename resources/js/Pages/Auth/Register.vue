<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-100">
      <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Reģistrācija</h2>
        <form @submit.prevent="register">
          <div class="mb-4">
            <label class="block text-gray-700">Vārds</label>
            <input v-model="form.name" type="text" class="input-field" required />
          </div>
          <div class="mb-4">
            <label class="block text-gray-700">E-pasts</label>
            <input v-model="form.email" type="email" class="input-field" required />
          </div>
          <div class="mb-4">
            <label class="block text-gray-700">Parole</label>
            <input v-model="form.password" type="password" class="input-field" required />
          </div>
          <div class="mb-4">
            <label class="block text-gray-700">Apstiprināt paroli</label>
            <input v-model="form.password_confirmation" type="password" class="input-field" required />
          </div>
          <button type="submit" class="btn">Reģistrēties</button>
          <p class="mt-4 text-center">
            Jau ir konts? <router-link to="/login" class="text-red-500">Pieteikties</router-link>
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
      const form = { name: "", email: "", password: "", password_confirmation: "" };
  
      const register = async () => {
        try {
            const response = await axios.post("/register", form);
            console.log(response.data);
            router.push("/kanban");
        } catch (error) {
            console.error("Registration failed", error.response?.data);
            // You can display error messages here
            alert("Registration failed: " + error.response?.data?.message);
        }
        };
  
      return { form, register };
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
  