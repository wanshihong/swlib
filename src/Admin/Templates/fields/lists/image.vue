<div id="{{ field.elemId }}" class="vue-image-app d-flex flex-wrap gap-2">
<div v-if="paths.length">
  <img class="rounded shadow-lg image-item"
       v-for="(path,index) in paths" @click="show(index)"
       :class="config.classes.join(' ')"
       v-bind="attributes"
       style="max-width: 60px;max-height: 40px"
       :src="path" alt="">
</div>
<span v-if="!paths.length" class="badge user-select-none rounded-0">未赋值</span>
</div>
<script>
pageConfig['{{ field.elemId }}'] = JSON.parse('{{ field|json_encode|raw }}');
</script>
