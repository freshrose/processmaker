<template>
  <div class="settings-listing data-table">
    <basic-search class="mb-3" @submit="onSearch"></basic-search>
    <div class="card card-body table-card">
      <b-table
        class="settings-table table table-responsive-lg text-break m-0 h-100 w-100"
        :current-page="currentPage"
        :per-page="perPage"
        :items="dataProvider"
        :fields="fields"
        :sort-by="orderBy"
        :sort-desc="orderDesc"
        ref="table"
        :filter="searchQuery"
        show-empty
        responsive
      >
        <template v-slot:cell(name)="row">
          <div v-if="row.item.name">{{ $t(row.item.name) }}</div>
          <div v-else>{{ row.item.key }}</div>
          <b-form-text v-if="row.item.helper">{{ $t(row.item.helper) }}</b-form-text>
        </template>
        <template v-slot:cell(config)="row">
          <keep-alive>
            <component v-if="row.item" :ref="`settingComponent_${row.index}`" :key="row.item.key" :is="component(row.item)" @saved="onChange" v-model="row.item.config" :setting="settings[row.index]"></component>
          </keep-alive>
        </template>
        <template v-slot:cell(actions)="row">
          <template v-if="row.item && row.item.format !== 'boolean'">
            <span v-b-tooltip.hover :title="getTooltip(row)">
              <b-button :aria-label="$t('Edit')" :disabled="row.item.readonly" @click="onEdit(row)" variant="link" size="lg"><i class="fa fa-pen-square"></i></b-button>
            </span>
            <b-button :aria-label="$t('Copy to Clipboard')" @click="onCopy(row)" variant="link" size="lg" v-b-tooltip.hover :title="$t('Copy to Clipboard')"><i class="fa fa-paste"></i></b-button>

            <span v-b-tooltip.hover v-if="!['boolean', 'object', 'button'].includes(row.item.format)" :title="$t('Clear')">
              <b-button :aria-label="$t('Clear')" :disabled="row.item.readonly" @click="onClear(row)" variant="link" size="lg"><i class="fas fa-trash-alt"></i></b-button>
            </span>
            <span v-else class="invisible">
              <b-button variant="link" size="lg"><i class="fas fa-trash-alt"></i></b-button>
            </span>
          </template>
        </template>
        <template v-slot:bottom-row><div class="bottom-padding"></div></template>
        <template v-slot:emptyfiltered>
          <div class="h-100 w-100 text-center">
            No Data Available
          </div>
        </template>
      </b-table>
      <div class="text-right p-2">
        <b-button
          v-for="(btn,index) in buttons"
          :key="`btn-${index}`"
          class="ml-2"
          v-bind="btn.ui.props"
          @click="handler(btn)"
          >{{btn.name}}</b-button>
      </div>
      <div v-if="totalRows" class="settings-table-footer text-secondary d-flex align-items-center p-2 w-100">
        <div class="flex-grow-1">
          <span v-if="totalRows">
            <span v-if="from == to">
              {{from}}
            </span>
            <span v-else>
              {{from}} - {{to}}
            </span>
            of {{totalRows}}
            <span v-if="totalRows == 1">
              {{ $t('Setting') }}
            </span>
            <span v-else>
              {{ $t('Settings') }}
            </span>
          </span>
        </div>
        <b-pagination
          class="m-0"
          v-model="currentPage"
          :total-rows="totalRows"
          :per-page="perPage"
          hide-ellipsis
          limit="3"
        >
          <template v-slot:first-text><i class="fas fa-step-backward fa-sm"></i></template>
          <template v-slot:last-text><i class="fas fa-step-forward fa-sm"></i></template>
          <template v-slot:prev-text><i class="fas fa-caret-left fa-lg" style="padding-top: 9px;"></i></template>
          <template v-slot:next-text><i class="fas fa-caret-right fa-lg" style="padding-top: 9px;"></i></template>
        </b-pagination>
      </div>
    </div>
  </div>
</template>


<script>
import { BasicSearch } from "SharedComponents";
import isPMQL from "../../../modules/isPMQL";
import SettingBoolean from './SettingBoolean';
import SettingCheckboxes from './SettingCheckboxes';
import SettingChoice from './SettingChoice';
import SettingFile from './SettingFile';
import SettingObject from './SettingObject';
import SettingScreen from './SettingScreen';
import SettingText from './SettingText';
import SettingTextArea from './SettingTextArea';
import SettingsImport from './SettingsImport';
import SettingsExport from './SettingsExport';

export default {
  components: {
    BasicSearch,
    SettingBoolean,
    SettingChoice,
    SettingCheckboxes,
    SettingFile,
    SettingObject,
    SettingScreen,
    SettingText,
    SettingTextArea,
    SettingsImport,
    SettingsExport
  },
  props: ['group'],
  data() {
    return {
      tableKey: 0,
      buttons: [],
      currentPage: 1,
      fields: [],
      filter: '',
      from: 0,
      orderBy: 'name',
      orderDesc: false,
      orderByPrevious: 'name',
      orderDescPrevious: false,
      perPage: 25,
      pmql: '',
      searchQuery: '',
      settings: [],
      to: 0,
      totalRows: 0,
      url: '/settings'
    };
  },
  computed: {
    orderDirection() {
      if (this.orderDesc) {
        return 'DESC';
      } else {
        return 'ASC';
      }
    },
  },
  mounted() {
    if (! this.group) {
      this.orderBy = "group";
      this.fields.push({
        key: "group",
        label: "Group",
        sortable: true,
        tdClass: "td-group",
      });
    }

    this.fields.push({
      key: "name",
      label: "Setting",
      sortable: true,
      tdClass: "td-name",
    });

    this.fields.push({
      key: "config",
      label: "Configuration",
      sortable: false,
      tdClass: "align-middle td-config",
    });

    this.fields.push({
      key: "actions",
      label: "",
      sortable: false,
      tdClass: "text-right",
    });

    this.loadButtons();
  },
  methods: {
    loadButtons() {
      ProcessMaker.apiClient.get(`/settings/group/${this.group}/buttons`)
        .then((response) => {
          this.buttons = response.data;
        });
    },
    apiGet() {
      return ProcessMaker.apiClient.get(this.pageUrl(this.currentPage));
    },
    apiPut(setting) {
      return ProcessMaker.apiClient.put(this.settingUrl(setting.id), setting);
    },
    component(setting) {
      switch (setting.format) {
        case 'text':
        case 'boolean':
        case 'checkboxes':
        case 'choice':
          return `setting-${setting.format}`;
        case 'object':
          if (setting.ui && setting.ui.format && setting.ui.format == 'map') {
            return `setting-object`;
          }
          if (setting.ui && setting.ui.format && setting.ui.format == 'screen') {
            return `setting-screen`;
          }
        case 'component':
          return window['__setting_component_' + setting.ui.component];
        case 'file':
          return 'setting-file';
        default:
          return 'setting-text-area';
      }
    },
    dataProvider(context, callback) {
      this.filter = '';
      this.pmql = '';
      if (this.searchQuery.isPMQL()) {
        this.pmql = this.searchQuery;
      } else {
        this.filter = this.searchQuery;
      }
      this.orderDesc = context.sortDesc;
      this.orderBy = context.sortBy;
      this.apiGet().then(response => {
        this.settings = response.data.data;
        this.totalRows = response.data.meta.total;
        this.from = response.data.meta.from;
        this.to = response.data.meta.to;
        if (this.orderBy !== this.orderByPrevious || this.orderDesc !== this.orderDescPrevious) {
          callback([]);
        }
        this.orderByPrevious = this.orderBy;
        this.orderDescPrevious = this.orderDesc;
        this.$nextTick(() => {
          callback(this.settings);
        });
      });
    },
    getTooltip(row) {
      if (row.item.readonly) {
        return this.$t('Read Only');
      } else {
        return this.$t('Edit');
      }
    },
    onChange(setting) {
      this.$nextTick(() => {
        this.apiPut(setting).then(response => {
          if (response.status == 204) {
            ProcessMaker.alert(this.$t("The setting was updated."), "success");
            if (_.get(setting, 'ui.refreshOnSave')) {
              this.$emit('refresh-all');
            } else {
              this.refresh();
            }
          }
        })
      });
    },
    onCopy(row) {
      let value = '';
      if (typeof row.item.config == 'string') {
        value = row.item.config;
      } else {
        value = JSON.stringify(row.item.config);
      }

      navigator.clipboard.writeText(value).then(() => {
        ProcessMaker.alert(this.$t("The setting was copied to your clipboard."), "success");
      }, () => {
        ProcessMaker.alert(this.$t("The setting was not copied to your clipboard."), "danger");
      });
    },
    onClear(row) {
      if (['array', 'checkboxes'].includes(row.item.format)) {
        row.item.config = [];
      }
      else {
        row.item.config = null;
      }
      this.onChange(row.item);
    },
    onEdit(row) {
      this.$refs[`settingComponent_${row.index}`].onEdit();
    },
    onSearch(query) {
      this.searchQuery = query;
    },
    pageUrl(page) {
      let url = `${this.url}?` +
        `page=${page}&` +
        `per_page=${this.perPage}&` +
        `order_by=${encodeURIComponent(this.orderBy)}&` +
        `order_direction=${this.orderDirection}&` +
        `filter=${this.filter}&` +
        `pmql=${this.pmql}&` +
        `group=${this.group}`;

      if (this.additionalPmql && this.additionalPmql.length) {
        url += `&additional_pmql=${this.additionalPmql}`;
      }

      return url;
    },
    settingUrl(id) {
      return `${this.url}/${id}`;
    },
    /**
     * Javascript handler for configuration button
     * 
     *  props: Properties of the button
     *  handler: JavaScript global function
     *
     * Example of Settings properties:
     *   name=Test button
     *   format=button
     *   hidden=true
     *   ui={"props":{"variant":"primary"},"handler":"mailTest"}
     * 
     */
    handler(btn) {
      if (btn.ui && btn.ui.handler && window[btn.ui.handler]) {
        window[btn.ui.handler](this);
      }
    },
    refresh() {
      this.$refs.table.refresh();
      this.$emit('refresh');
    }
  }
};
</script>

<style lang="scss">
@import '../../../../sass/colors';

.preview-renderer {
  .form-group {
    margin: 0 !important;
    padding: 0 !important;
  }
}

.b-pagination {
  .page-item {
    .page-link {
      background-color: lighten($secondary, 44%);
      border-radius: 2px;
      color: $secondary;
      cursor: pointer;
      font-size: 12px;
      height: 29px;
      line-height: 29px;
      margin: 1px;
      padding: 0;
      text-align: center;
      width: 29px;
    }
    &:hover {
      .page-link {
        background-color: lighten($secondary, 40%);
      }
    }
    &.disabled {
      cursor: not-allowed;
      opacity: .5;
      .page-link {
        background-color: lighten($secondary, 44%);
      }
    }
    &.active {
      .page-link {
        background-color: lighten($secondary, 15%);
        color: white;
      }
      &:hover {
        .page-link {
          background-color: lighten($secondary, 11%);
        }
      }
    }
  }
}
</style>
