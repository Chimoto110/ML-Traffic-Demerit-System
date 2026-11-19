SELECT model_version, k, wcss, silhouette, trained_at FROM segment_models;
SELECT segment_label, COUNT(*) as cnt, AVG(distance_to_centroid) as avg_dist FROM driver_segments GROUP BY segment_label ORDER BY segment_label;
SELECT is_atypical, COUNT(*) as cnt FROM driver_segments GROUP BY is_atypical;
