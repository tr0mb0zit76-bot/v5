export const gridDensityOptions = [
  {
    key: 'compact',
    label: 'Компактно',
    fontSize: '12px',
    rowHeight: '34px',
    headerHeight: '36px',
  },
  {
    key: 'normal',
    label: 'Нормально',
    fontSize: '13px',
    rowHeight: '40px',
    headerHeight: '42px',
  },
  {
    key: 'comfortable',
    label: 'Свободно',
    fontSize: '14px',
    rowHeight: '46px',
    headerHeight: '48px',
  },
];

export const defaultGridDensity = 'normal';

export function resolveGridDensity(key) {
  return gridDensityOptions.find((option) => option.key === key) ?? gridDensityOptions[1];
}
