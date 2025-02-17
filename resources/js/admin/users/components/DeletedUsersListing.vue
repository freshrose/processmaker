<template>
  <div class="data-table">
    <data-loading
      :for="/deleted_users\?page/"
      v-show="shouldShowLoader"
      :empty="$t('No Data Available')"
      :empty-desc="$t('')"
      empty-icon="noData"
    />
    <div v-show="!shouldShowLoader" class="card card-body table-card">
      <vuetable
        :dataManager="dataManager"
        :sortOrder="sortOrder"
        :css="css"
        :api-mode="false"
        @vuetable:pagination-data="onPaginationData"
        :fields="fields"
        :data="data"
        data-path="data"
        :noDataTemplate="$t('No Data Available')"
        pagination-path="meta"
      >
        <template slot="avatar" slot-scope="props">
          <avatar-image size="25" :input-data="props.rowData" hide-name="true"></avatar-image>
        </template>
        <template slot="actions" slot-scope="props">
          <div class="actions">
            <div class="popout">
              <b-btn
                variant="link"
                @click="restoreUser(props.rowData, props.rowIndex)"
                v-b-tooltip.hover
                :title="$t('Restore User')"
              >
                <i class="fas fa-trash-restore fa-lg fa-fw"></i>
              </b-btn>
            </div>
          </div>
        </template>
      </vuetable>
      <pagination
        :single="$t('Deleted User')"
        :plural="$t('Deleted Users')"
        :perPageSelectEnabled="true"
        @changePerPage="changePerPage"
        @vuetable-pagination:change-page="onPageChange"
        ref="pagination"
      ></pagination>
    </div>
  </div>
</template>


<script>
import datatableMixin from "../../../components/common/mixins/datatable";
import dataLoadingMixin from "../../../components/common/mixins/apiDataLoading";
import AvatarImage from "../../../components/AvatarImage";
Vue.component("avatar-image", AvatarImage);

export default {
  mixins: [datatableMixin, dataLoadingMixin],
  props: ["filter", "permission"],
  data() {
    return {
      localLoadOnStart: false,
      orderBy: "username",
      data: [],
      // Our listing of users
      sortOrder: [
        {
          field: "username",
          sortField: "username",
          direction: "asc"
        }
      ],
      fields: [
        {
          title: () => this.$t("ID"),
          name: "id",
          sortField: "id"
        },
        {
          title: () => this.$t("Username"),
          name: "username",
          sortField: "username"
        },
        {
          title: () => this.$t("Full Name"),
          name: "fullname",
          sortField: "fullname"
        },
        {
          title: () => this.$t("Avatar"),
          name: "__slot:avatar",
          field: "user"
        },
        {
          title: () => this.$t("Status"),
          name: "status",
          sortField: "status",
          callback: this.formatStatus
        },
        {
          title: () => this.$t("Modified"),
          name: "updated_at",
          sortField: "updated_at",
          callback: "formatDate"
        },
        {
          title: () => this.$t("Created"),
          name: "created_at",
          sortField: "created_at",
          callback: "formatDate"
        },
        {
          title: () => this.$t("Last Login"),
          name: "loggedin_at",
          sortField: "loggedin_at",
          callback: "formatDate"
        },
        {
          name: "__slot:actions",
          title: ""
        }
      ]
    };
  },
  created() {
    ProcessMaker.EventBus.$on("api-data-deleted-users", (val) => {
      this.localLoadOnStart = val;
      this.fetch();
      this.apiDataLoading = false;
      this.apiNoResults = false;
    });
  },
  methods: {
    formatStatus(status) {
      status = status.toLowerCase();
      let bubbleColor = {
        active: "text-success",
        inactive: "text-danger",
        draft: "text-warning",
        archived: "text-info"
      };
      return (
        '<i class="fas fa-circle ' +
        bubbleColor[status] +
        ' small"></i><span class="text-capitalize"> ' +
        this.$t(status.charAt(0).toUpperCase() + status.slice(1)) +
        '</span>'
      );
    },
    restoreUser(data, index) {
      const $body = {
        id: data.id
      };

      ProcessMaker.confirmModal(
        this.$t('Caution!'),
        this.$t('Are you sure you want to restore the user {{item}}?', {item: data.fullname}),
        "",
        () => {
          ProcessMaker.apiClient.put('users/restore', $body).then(response => {
            ProcessMaker.alert(
              this.$t('The user was restored'),
              "success"
            );
            ProcessMaker.EventBus.$emit("api-data-deleted-users", true);
          });
        }
      );
    },
    fetch() {
      if (!this.localLoadOnStart) {
        this.data = [];
        return;
      }
      this.loading = true;
      // Change method sort by user
      this.orderBy = this.orderBy === "fullname" ? "firstname" : this.orderBy;
      // Load from our api client
      ProcessMaker.apiClient
        .get(
          "deleted_users?page=" +
            this.page +
            "&per_page=" +
            this.perPage +
            "&filter=" +
            this.filter +
            "&order_by=" +
            this.orderBy +
            "&order_direction=" +
            this.orderDirection
        )
        .then(response => {
          this.data = this.transform(response.data);
          this.loading = false;
        });
    }
  }
};
</script>

<style lang="scss" scoped>
</style>
