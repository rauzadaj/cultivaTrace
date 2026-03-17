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
    mdAndUp: computed(() => width.value >= 960),
  }
}
