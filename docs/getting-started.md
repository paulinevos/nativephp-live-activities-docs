---
search: false
head:
  - - meta
    - name: robots
      content: noindex
---

<script setup>
import { onMounted } from 'vue'
import { withBase } from 'vitepress'

onMounted(() => {
  window.location.replace(withBase('/') + window.location.search + window.location.hash)
})
</script>

# Getting started has moved

Continue to [Getting started](/).
