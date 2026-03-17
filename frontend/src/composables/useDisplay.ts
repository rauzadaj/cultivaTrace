import { computed, onMounted, onUnmounted, ref } from 'vue'

const width = ref(typeof window === 'undefined' ? 1440 : window.innerWidth)

function updateWidth() {
  width.value = window.innerWidth
}

export function useDisplay() {
  onMounted(() => {
    updateWidth()
    window.addEventListener('resize', updateWidth)
  })

  onUnmounted(() => {
    window.removeEventListener('resize', updateWidth)
  })

  return {
    width,
    xs: computed(() => width.value < 768),
    smAndUp: computed(() => width.value >= 768),
    mdAndUp: computed(() => width.value >= 960),
    tablet: computed(() => width.value >= 768 && width.value <= 1024),
    desktop: computed(() => width.value > 1024),
  }
}
