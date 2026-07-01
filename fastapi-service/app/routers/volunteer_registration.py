from fastapi import FastAPI, HTTPException, Depends
from sqlalchemy.orm import Session
#from app.routers.volunteer_registration import router
from fastapi import APIRouter
from app.database import SessionLocal
from app.models import EventRegistration
from app.schemas import VolunteerRegistrationRequest

router = APIRouter()


def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()


@router.post("/volunteers/register/check")
def check_registration(
    request: VolunteerRegistrationRequest,
    db: Session = Depends(get_db)
):

    registration = (
        db.query(EventRegistration)
        .filter(
            EventRegistration.volunteer_id == request.volunteer_id,
            EventRegistration.status == "active"
        )
        .first()
    )

    if registration:

        raise HTTPException(
            status_code=409,
            detail="Volunteer is already registered in another active event."
        )

    return {
        "allowed": True,
        "message": "Volunteer can register."
    }