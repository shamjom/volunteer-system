from pydantic import BaseModel

class VolunteerRegistrationRequest(BaseModel):
    volunteer_id: int
    event_id: int