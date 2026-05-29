import { createApp } from 'vue'
import { Quasar, Notify, Loading } from 'quasar'
import '@quasar/extras/material-icons/material-icons.css'
import '@mdi/font/css/materialdesignicons.css'
import 'quasar/src/css/index.sass'
import App from './App.vue'
import { pinia } from './plugins/pinia'
import router from './router'
import { useAuthStore } from './stores/auth'

const app = createApp(App)
const authStore = useAuthStore(pinia)

app.use(pinia)
app.use(router)
app.use(Quasar, {
  plugins: {
    Notify,
    Loading,
  },
  config: {
    brand: {
      primary: '#1B6B3A',
    },
  },
})

authStore.init().finally(() => {
  app.mount('#app')
})
