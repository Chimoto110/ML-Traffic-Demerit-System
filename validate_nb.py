import json

nb_path = r"C:\Users\chimo\Downloads\ML-Traffic-Demerit-System-main\ML-Traffic-Demerit-System-main\apps\backend\traffic-demerit-web\ml_service\02_risk_model_training.ipynb"
with open(nb_path, encoding='utf-8') as f:
    nb = json.load(f)

print(f"Valid notebook with {len(nb['cells'])} cells")
for i, c in enumerate(nb['cells']):
    source_len = len(''.join(c.get('source', [])))
    print(f"  Cell {i}: {c['cell_type']} ({source_len} chars)")

# Verify key sections exist
sources = []
for c in nb['cells']:
    sources.append(''.join(c.get('source', [])))

full_text = '\n'.join(sources)
checks = [
    ('DATA_SOURCE', 'DATA_SOURCE' in full_text),
    ('INCLUDE_SYNTHETIC', 'INCLUDE_SYNTHETIC' in full_text),
    ('LABEL_HORIZON_DAYS', 'LABEL_HORIZON_DAYS' in full_text),
    ('CUTOFF_EVERY_DAYS', 'CUTOFF_EVERY_DAYS' in full_text),
    ('MIN_TRAIN_ROWS', 'MIN_TRAIN_ROWS' in full_text),
    ('files.upload', 'files.upload' in full_text),
    ('SPLIT_DATE', 'SPLIT_DATE' in full_text),
    ('CalibratedClassifierCV', 'CalibratedClassifierCV' in full_text),
    ('shap', 'shap' in full_text.lower() or 'TreeExplainer' in full_text),
    ('files.download', 'files.download' in full_text),
    ('zipfile', 'zipfile' in full_text),
]
print("\nContent checks:")
for name, result in checks:
    status = "PASS" if result else "FAIL"
    print(f"  {status}: {name}")
