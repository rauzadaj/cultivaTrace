import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

export default createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: 'cultivaTrace',
    themes: {
      cultivaTrace: {
        colors: {
          primary: '#195b39',
          secondary: '#8b5e34',
          accent: '#d97706',
          background: '#f3efe4',
          surface: '#fffdf7',
          success: '#2f855a',
          warning: '#c05621',
          error: '#9b2c2c',
        },
      },
    },
  },
})
