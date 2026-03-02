
import * as DynamicTable from 'core_table/dynamic';
import DynamicForm from 'core_form/dynamicform';

export const initTable = uniqid => {
    const schoolFormContainer = document.querySelector('#schoolform_container');
    if (!schoolFormContainer) {
        return false;
    }
    const schoolForm = new DynamicForm(schoolFormContainer, schoolFormContainer.dataset.formClass);
    if (!schoolForm) {
        return false;
    }

    schoolForm.addEventListener(schoolForm.events.FORM_SUBMITTED, (e) => {
        e.preventDefault();
        const response = e.detail;
        if (response.filterset) {
            const dTable = DynamicTable.getTableFromId(uniqid);
            if (dTable) {
                DynamicTable.setFilters(dTable, response.filterset);
            }
        }
    });
};
