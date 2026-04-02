export const gridDensityOptions = [
  {
    key: 'compact',
    label: 'Компактно',
    fontSize: '12px',
    rowHeight: '36px',
    headerHeight: '38px',
  },
  {
    key: 'normal',
    label: 'Нормально',
    fontSize: '13px',
    rowHeight: '42px',
    headerHeight: '44px',
  },
  {
    key: 'comfortable',
    label: 'Свободно',
    fontSize: '14px',
    rowHeight: '48px',
    headerHeight: '50px',
  },
];

export const defaultGridDensity = 'normal';

export function resolveGridDensity(key) {
  return gridDensityOptions.find((option) => option.key === key) ?? gridDensityOptions[1];
}
