import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'

import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

export default createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        colors: {
          primary: '#2e7d32',
          secondary: '#8d6e63',
          accent: '#ffb300',
          background: '#f4f7f2',
          surface: '#ffffff',
        },
      },
    },
  },
})
