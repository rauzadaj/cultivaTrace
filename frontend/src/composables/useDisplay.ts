import { computed } from 'vue'
import { useQuasar } from 'quasar'

export function useDisplay() {
  const $q = useQuasar()
  const width = computed(() => $q.screen.width)
  const height = computed(() => $q.screen.height)

  return {
    width,
    height,
    xs: computed(() => width.value < 768),
    smAndUp: computed(() => width.value >= 768),
    mdAndUp: computed(() => width.value >= 1024),
    tablet: computed(() => width.value >= 768 && width.value < 1024),
    desktop: computed(() => width.value >= 1024),
    portrait: computed(() => height.value > width.value),
    landscape: computed(() => width.value >= height.value),
  }
}
