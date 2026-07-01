from datetime import datetime
from app.models import Event
from app.models import EventRegistration


class EventService:

    @staticmethod
    def volunteer_has_active_event(db, volunteer_id):

        registration = (
            db.query(EventRegistration)
            .join(
                Event,
                Event.id == EventRegistration.event_id
            )
            .filter(
                 EventRegistration.volunteer_id == volunteer_id,
                Event.end_date > datetime.utcnow()
            )
            .first()
        )

        return registration

@staticmethod
def current_event(db, volunteer_id):

    return (
        db.query(Event)
        .join(
            EventRegistration,
            Event.id == EventRegistration.event_id
        )
        .filter(
            EventRegistration.volunteer_id == volunteer_id,
            Event.end_date > datetime.utcnow()
        )
        .first()
    )

@staticmethod
def upcoming_events(db):

    return (
        db.query(Event)
        .filter(
            Event.start_date > datetime.utcnow()
        )
        .all()
    )
    
@staticmethod
def volunteer_events(db, volunteer_id):

    return (
        db.query(Event)
        .join(
            EventRegistration,
            Event.id == EventRegistration.event_id
        )
        .filter(
            EventRegistration.volunteer_id == volunteer_id
        )
        .all()
    )
    
    
