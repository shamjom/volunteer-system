from sqlalchemy.orm import DeclarativeBase
from sqlalchemy import Column,Integer,String,DateTime,ForeignKey


class Base(DeclarativeBase):
    pass


class Event(Base):

    __tablename__ = "events"

    id = Column(Integer, primary_key=True)

    title = Column(String)

    start_date = Column(DateTime)

    end_date = Column(DateTime)


class EventRegistration(Base):

    __tablename__ = "event_registrations"

    id = Column(Integer, primary_key=True)

    volunteer_id = Column(Integer)

    event_id = Column(Integer, ForeignKey("events.id"))

    status = Column(String)