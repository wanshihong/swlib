<div id="{{ field.elemId }}" class="vue-image-app row mb-3">

    <label :for="config.elemId"
           class="col-sm-2 col-form-label text-end"
           :class="config.classes.join(' ')"
           v-bind="attributes"
    >
      [[ config.label ]]
    </label>
    <div class="col-sm-10 d-grid row-gap-2">

        <div class=" border d-flex justify-content-center align-items-center p-2 position-relative"
             v-for="(path,index) in paths" :key="index"
        >

          <img class="image img-fluid image-item" :src="path" style="max-height: 80px" @click.stop="show(index)" alt="">

          <button type="button" class="btn btn-danger rounded-0 btn-sm upload-action" @click.stop="del(index)">
            <i class="bi bi-trash"></i>
          </button>
        </div>


        <div class=" border d-flex justify-content-center align-items-center p-2 position-relative"
             @click="upload"
             v-if="paths.length<config.max"
        >
          <button type="button" class="btn btn-light rounded-0 flex-fill ">
            <i class="icon bi bi-cloud-upload fs-2"></i>
          </button>
        </div>


        <input type="text" class="form-control visually-hidden" id="{{ field.elemId }}" :name="config.field"
               :required="config.required"
               :value="value">
        <div class="invalid-feedback">
          请上传[[ config.label ]]
        </div>
        <div class="form-text" v-if="config.helpText" v-html="config.helpText"></div>
    </div>

</div>

<script>
pageConfig['{{ field.elemId }}'] = JSON.parse('{{ field|json_encode|raw }}');
</script>
