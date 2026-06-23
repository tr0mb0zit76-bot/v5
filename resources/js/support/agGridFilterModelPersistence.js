/**
 * @param {string} gridKey e.g. tasks, disposition
 * @param {string|number} userId
 */
export function agGridFilterModelStorageKey(gridKey, userId) {
  return `${gridKey}_grid_filter_model_v1_${userId}`;
}

/**
 * @param {import('ag-grid-community').GridApi | null | undefined} api
 * @param {string} storageKey
 */
export function loadAgGridFilterModel(api, storageKey) {
  if (!api || !storageKey) {
    return;
  }

  const raw = localStorage.getItem(storageKey);
  if (!raw) {
    return;
  }

  try {
    const model = JSON.parse(raw);
    if (model && typeof model === 'object') {
      api.setFilterModel(model);
    }
  } catch (error) {
    console.error('Error loading grid filter model', error);
  }
}

/**
 * @returns {(api: import('ag-grid-community').GridApi | null | undefined, storageKey: string) => void}
 */
export function createAgGridFilterModelPersister() {
  /** @type {ReturnType<typeof setTimeout> | null} */
  let saveTimeout = null;

  return (api, storageKey) => {
    if (!api || !storageKey) {
      return;
    }

    if (saveTimeout) {
      clearTimeout(saveTimeout);
    }

    saveTimeout = setTimeout(() => {
      try {
        const model = api.getFilterModel();
        localStorage.setItem(storageKey, JSON.stringify(model ?? {}));
      } catch (error) {
        console.error('Error saving grid filter model', error);
      }
    }, 250);
  };
}
