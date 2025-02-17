<template>
    <div class="data-table">
        <div class="card card-body table-card">
            <vuetable
                    :dataManager="dataManager"
                    :sortOrder="sortOrder"
                    :css="css"
                    :api-mode="false"
                    @vuetable:pagination-data="onPaginationData"
                    :fields="fields"
                    :data="data"
                    data-path="data"
                    pagination-path="meta"
                    :noDataTemplate="$t('No Data Available')"
            >
                <template slot="actions" slot-scope="props">
                    <div class="actions">
                        <div class="popout">
                            <b-btn
                                    variant="link"
                                    @click="onDelete( props.rowData, props.rowIndex)"
                                    v-b-tooltip.hover
                                    :title="$t('Remove from Group')"
                            >
                                <i class="fas fa-minus-circle fa-lg fa-fw"></i>
                            </b-btn>
                        </div>
                    </div>
                </template>
            </vuetable>
            <pagination
                    :single="$t('User')"
                    :plural="$t('Users')"
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

  export default {
    mixins: [datatableMixin],
    props: ["filter", "groupId"],
    data() {
      return {
        orderBy: "name",
        // Our listing of users
        sortOrder: [
          {
            field: "name",
            sortField: "name",
            direction: "asc"
          }
        ],
        fields: [
          {
            title: () => this.$t("ID"),
            name: "id"
          },
          {
            title: () => this.$t("Name"),
            name: "name",
            sortField: "name"
          },
          {
            title: () => this.$t("Status"),
            name: "status",
            sortField: "status",
            callback: this.formatStatus
          },
          {
            name: "__slot:actions",
            title: ""
          }
        ]
      };
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
      onEdit(data, index) {
        window.location = "/admin/groups/" + data.id + "/edit";
      },
      onDelete(data, index) {
        let that = this;
        ProcessMaker.confirmModal(
          this.$t("Caution!"),
          this.$t('Are you sure you want to delete {{item}}?', {item: data.name}),
          null,
          function () {
            ProcessMaker.apiClient
              .delete("group_members/" + data.id)
              .then(response => {
                ProcessMaker.alert(this.$t("The group was removed from the group."), "success");
                that.fetch();
              });
          }
        );
      },
      onAction(action, data, index) {
        switch (action) {
          case "users-item":
            //todo
            break;
          case "permissions-item":
            //todo
            break;
        }
      },
      fetch() {
        this.loading = true;
        this.orderBy = this.orderBy === "name" ? "name" : this.orderBy;
        // Load from our api client
        ProcessMaker.apiClient
          .get(
            "groups/" + this.groupId + "/groups" +
            "?page=" +
            this.page +
            "&per_page=" +
            this.perPage +
            "&filter=" +
            this.filter +
            "&group_id=" +
            this.groupId +
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
    >>> th#_total_users {
        width: 150px;
        text-align: center;
    }

    >>> .vuetable-th-status {
        min-width: 90px;
    }

    >>> .vuetable-th-members_count {
        min-width: 90px;
    }
</style>
