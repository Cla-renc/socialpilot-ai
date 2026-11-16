import 'package:flutter/material.dart';

class PublishingAttempt {
  final int id;
  final String platform;
  final String status;
  final String? contentType;
  final String? externalPostId;
  final String? externalPostUrl;
  final String? errorCode;
  final String? errorMessage;
  final int attemptNumber;
  final bool isRetryable;
  final DateTime? publishedAt;
  final int? brandId;
  final int? scheduledPostId;
  final int? socialAccountId;
  final DateTime createdAt;
  final DateTime updatedAt;

  const PublishingAttempt({
    required this.id,
    required this.platform,
    required this.status,
    this.contentType,
    this.externalPostId,
    this.externalPostUrl,
    this.errorCode,
    this.errorMessage,
    this.attemptNumber = 1,
    this.isRetryable = false,
    this.publishedAt,
    this.brandId,
    this.scheduledPostId,
    this.socialAccountId,
    required this.createdAt,
    required this.updatedAt,
  });

  factory PublishingAttempt.fromJson(Map<String, dynamic> json) {
    return PublishingAttempt(
      id: json['id'] as int,
      platform: json['platform'] as String,
      status: json['status'] as String,
      contentType: json['content_type'] as String?,
      externalPostId: json['external_post_id'] as String?,
      externalPostUrl: json['external_post_url'] as String?,
      errorCode: json['error_code'] as String?,
      errorMessage: json['error_message'] as String?,
      attemptNumber: (json['attempt_number'] as int?) ?? 1,
      isRetryable: (json['is_retryable'] as bool?) ?? false,
      publishedAt: json['published_at'] != null
          ? DateTime.parse(json['published_at'] as String)
          : null,
      brandId: json['brand_id'] as int?,
      scheduledPostId: json['scheduled_post_id'] as int?,
      socialAccountId: json['social_account_id'] as int?,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }

  bool get isPublished => status == 'published';
  bool get isFailed => status == 'failed';
  bool get isProcessing => status == 'processing' || status == 'publishing';
  bool get isCancelled => status == 'cancelled';

  Color get statusColor {
    switch (status) {
      case 'published':
        return const Color(0xFF4CAF50);
      case 'failed':
        return const Color(0xFFF44336);
      case 'processing':
      case 'publishing':
        return const Color(0xFFFFC107);
      case 'cancelled':
        return const Color(0xFF9E9E9E);
      case 'partially_published':
        return const Color(0xFFFF9800);
      default:
        return const Color(0xFF2196F3);
    }
  }
}
