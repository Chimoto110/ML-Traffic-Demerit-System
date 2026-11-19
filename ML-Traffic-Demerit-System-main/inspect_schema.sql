SHOW CREATE TABLE users;
SHOW CREATE TABLE roles;
SHOW CREATE TABLE driver_profiles;
SHOW CREATE TABLE vehicles;
SHOW CREATE TABLE stations;
SHOW CREATE TABLE officer_assignments;
SHOW CREATE TABLE violations;
SHOW CREATE TABLE demerit_ledger_entries;
SHOW CREATE TABLE payments;
SHOW CREATE TABLE sanction_actions;
SHOW CREATE TABLE appeals;
SHOW CREATE TABLE review_logs;
SHOW CREATE TABLE risk_predictions;
SHOW CREATE TABLE risk_assessments;
SHOW CREATE TABLE system_notifications;
SHOW CREATE TABLE login_audits;

SELECT 'users' as tbl, COUNT(*) as cnt FROM users UNION ALL
SELECT 'roles', COUNT(*) FROM roles UNION ALL
SELECT 'driver_profiles', COUNT(*) FROM driver_profiles UNION ALL
SELECT 'vehicles', COUNT(*) FROM vehicles UNION ALL
SELECT 'stations', COUNT(*) FROM stations UNION ALL
SELECT 'officer_assignments', COUNT(*) FROM officer_assignments UNION ALL
SELECT 'violations', COUNT(*) FROM violations UNION ALL
SELECT 'demerit_ledger_entries', COUNT(*) FROM demerit_ledger_entries UNION ALL
SELECT 'payments', COUNT(*) FROM payments UNION ALL
SELECT 'sanction_actions', COUNT(*) FROM sanction_actions UNION ALL
SELECT 'appeals', COUNT(*) FROM appeals UNION ALL
SELECT 'review_logs', COUNT(*) FROM review_logs UNION ALL
SELECT 'risk_predictions', COUNT(*) FROM risk_predictions UNION ALL
SELECT 'risk_assessments', COUNT(*) FROM risk_assessments UNION ALL
SELECT 'system_notifications', COUNT(*) FROM system_notifications UNION ALL
SELECT 'login_audits', COUNT(*) FROM login_audits;
