# Rebuild: Restart the wiki after an update
rebuild:
	docker compose down -v
	docker compose up -d --build
