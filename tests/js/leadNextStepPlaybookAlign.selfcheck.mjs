import { isNextStepOffPlaybook } from '../../resources/js/support/leadNextStepPlaybookAlign.js';

function assert(cond, msg) {
    if (!cond) {
        throw new Error(msg);
    }
}

assert(
    isNextStepOffPlaybook(['Перезвонить по ставке'], ['Собрать полный пакет документов для договора']) === true,
    'callback vs documents should be off-playbook',
);

assert(
    isNextStepOffPlaybook(['Запросить документы у клиента'], ['Собрать полный пакет документов для договора']) === false,
    'documents task should align with documents playbook',
);

assert(
    isNextStepOffPlaybook([], ['Документы']) === false,
    'no tasks → not off-playbook',
);

assert(
    isNextStepOffPlaybook(['Любая задача'], []) === false,
    'no playbook hints → not off-playbook',
);

console.log('leadNextStepPlaybookAlign.selfcheck: ok');
