class PublishingNotification {
  final int id;
  final String type;
  final String title;
  final String body;
  final int? brandId;
  final int? publishingAttemptId;
  final DateTime? readAt;
  final DateTime createdAt;

  const PublishingNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    this.brandId,
    this.publishingAttemptId,
    this.readAt,
    required this.createdAt,
  });

  factory PublishingNotification.fromJson(Map<String, dynamic> json) =>
      PublishingNotification(
        id: json['id'] as int,
        type: json['type'] as String,
        title: json['title'] as String,
        body: json['body'] as String,
        brandId: json['brand_id'] as int?,
        publishingAttemptId: json['publishing_attempt_id'] as int?,
        readAt: json['read_at'] != null
            ? DateTime.parse(json['read_at'] as String)
            : null,
        createdAt: DateTime.parse(json['created_at'] as String),
      );

  bool get isUnread => readAt == null;
}
