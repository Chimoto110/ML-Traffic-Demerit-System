SELECT COUNT(*) as driver_features FROM ml_driver_features;
SELECT COUNT(*) as transactions FROM ml_transactions;
SELECT * FROM ml_driver_features LIMIT 5;
SELECT * FROM ml_transactions LIMIT 3;

SELECT reoffended_within_180_days, COUNT(*) as cnt FROM ml_driver_features GROUP BY reoffended_within_180_days;
SELECT new_driver, COUNT(*) as cnt FROM ml_driver_features GROUP BY new_driver;
