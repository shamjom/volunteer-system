from fastapi import FastAPI
from app.routers.volunteer_registration import router

app = FastAPI()

app.include_router(router)

@app.get("/")
def home():
    return {
        "message": "FastAPI is running"
    }