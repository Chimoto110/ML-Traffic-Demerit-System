SELECT COUNT(*) as rule_count FROM association_rules;
SELECT rule_family, COUNT(*) as cnt FROM association_rules GROUP BY rule_family;
SELECT antecedent, consequent, support, confidence, coverage, lift, plain_english FROM association_rules ORDER BY lift DESC LIMIT 5;
