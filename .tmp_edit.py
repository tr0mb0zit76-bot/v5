from pathlib import Path
p = Path('c:/OSPanel/home/v5.local/app/Services/OrderWizardService.php')
s = p.read_text(encoding='utf-8')
p.write_text(s, encoding='utf-8')
print('done')
