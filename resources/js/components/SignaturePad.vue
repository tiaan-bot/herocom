<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import SignaturePad from 'signature_pad'
import { Eraser, Undo2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

const props = withDefaults(defineProps<{ modelValue: string; disabled?: boolean }>(), {
  disabled: false,
})
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const canvas = ref<HTMLCanvasElement | null>(null)
let pad: SignaturePad | null = null
let observer: ResizeObserver | null = null

function syncModel(): void {
  if (!pad) {
    return
  }
  emit('update:modelValue', pad.isEmpty() ? '' : pad.toDataURL('image/png'))
}

// The wizard renders steps with v-show, so the canvas mounts hidden (0px).
// Re-fit to the rendered size whenever it changes, preserving any strokes.
function fit(): void {
  const el = canvas.value
  if (!el || !pad || el.offsetWidth === 0) {
    return
  }
  const ratio = Math.max(window.devicePixelRatio || 1, 1)
  const data = pad.toData()
  el.width = el.offsetWidth * ratio
  el.height = el.offsetHeight * ratio
  el.getContext('2d')?.scale(ratio, ratio)
  pad.clear()
  pad.fromData(data)
}

function clear(): void {
  pad?.clear()
  emit('update:modelValue', '')
}

function undo(): void {
  if (!pad) {
    return
  }
  const data = pad.toData()
  data.pop()
  pad.fromData(data)
  syncModel()
}

function applyDisabled(disabled: boolean): void {
  if (!pad) {
    return
  }
  disabled ? pad.off() : pad.on()
}

watch(() => props.disabled, applyDisabled)

onMounted(() => {
  if (!canvas.value) {
    return
  }
  pad = new SignaturePad(canvas.value, { penColor: '#191320', backgroundColor: '#ffffff' })
  pad.addEventListener('endStroke', syncModel)
  observer = new ResizeObserver(() => fit())
  observer.observe(canvas.value)
  applyDisabled(props.disabled)
})

onBeforeUnmount(() => {
  observer?.disconnect()
  pad?.off()
})
</script>

<template>
  <div>
    <div class="rounded-md border bg-white" :class="disabled ? 'pointer-events-none opacity-50' : ''">
      <canvas ref="canvas" class="h-44 w-full touch-none rounded-md" />
    </div>
    <div class="mt-2 flex gap-2">
      <Button type="button" variant="outline" size="sm" :disabled="disabled" @click="undo">
        <Undo2 class="size-4" /> Undo
      </Button>
      <Button type="button" variant="outline" size="sm" :disabled="disabled" @click="clear">
        <Eraser class="size-4" /> Clear
      </Button>
    </div>
  </div>
</template>
