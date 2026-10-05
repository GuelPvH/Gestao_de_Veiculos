FROM mysql:8.4
USER root
RUN microdnf install -y python3 && microdnf clean all
ENTRYPOINT ["python3"]
